<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Integration\Kernel;

use Nowo\McpKitBundle\Bridge\TransactionDoctrine\TransactionDoctrineWriteRunner;
use Nowo\McpKitBundle\Event\McpCallOutcome;
use Nowo\McpKitBundle\Event\McpToolCallEvent;
use Nowo\McpKitBundle\Protocol\JsonRpcError;
use Nowo\McpKitBundle\Protocol\McpProtocol;
use Nowo\McpKitBundle\Server\McpToolAttempt;
use Nowo\McpKitBundle\Server\McpToolInvoker;
use Nowo\McpKitBundle\Tests\Fixtures\AuditRecorder;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureAuthorizer;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureRateLimiter;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\LockingWriteTool;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteGetTool;
use Nowo\McpKitBundle\Tests\Fixtures\WriteUnit\FixtureWriteUnit;
use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Write\McpWriteConflict;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\WriteUnit\WriteUnitInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

use function array_map;

/**
 * Option `write_unit.enabled` with transaction-doctrine's real WriteUnit: write tools are replayed inside the invoker,
 * the write budget and the audit event happen once per call, read tools are never wrapped, and with the option off
 * nothing changes.
 */
#[CoversClass(McpToolInvoker::class)]
#[CoversClass(McpToolAttempt::class)]
#[CoversClass(TransactionDoctrineWriteRunner::class)]
#[CoversClass(McpWriteConflict::class)]
#[CoversClass(JsonRpcError::class)]
#[CoversClass(McpToolCall::class)]
final class WriteUnitTest extends KernelTestCase
{
    private function boot(bool $writeUnit = true, bool $policy = true): McpKitTestKernel
    {
        $services = [
            LockingWriteTool::class  => LockingWriteTool::class,
            NoteGetTool::class       => NoteGetTool::class,
            FixtureAuthorizer::class => FixtureAuthorizer::class,
            AuditRecorder::class     => AuditRecorder::class,
        ];
        if ($writeUnit) {
            $services[WriteUnitInterface::class] = FixtureWriteUnit::class;
        }
        if ($policy) {
            $services[ConflictResponsePolicy::class] = ConflictResponsePolicy::class;
        }

        return $this->bootKernel(['hooks' => ['authorizer' => FixtureAuthorizer::class], 'write_unit' => ['enabled' => $writeUnit]], $services);
    }

    private function lock(McpKitTestKernel $kernel, string $mode, int $id = 1): Response
    {
        return $this->modern($kernel, 'tools/call', ['name' => 'note_lock', 'arguments' => ['mode' => $mode]], [], 'tok-grace', $id);
    }

    /**
     * @return list<int>
     */
    private function attempts(McpKitTestKernel $kernel): array
    {
        return array_map(static fn (McpToolCall $call): int => $call->attempt, $this->service($kernel, LockingWriteTool::class)->calls);
    }

    private function unit(McpKitTestKernel $kernel): FixtureWriteUnit
    {
        $unit = $this->service($kernel, WriteUnitInterface::class);
        self::assertInstanceOf(FixtureWriteUnit::class, $unit);

        return $unit;
    }

    #[Test]
    public function aToolThatDeadlocksOnceSucceedsOnTheReplayWithOneAuditAndOneWriteHit(): void
    {
        $kernel   = $this->boot();
        $response = $this->lock($kernel, 'once');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $result = $this->json($response)['result'];
        self::assertFalse($result['isError']);
        self::assertSame(['attempt' => 2], $result['structuredContent']);
        self::assertSame([1, 2], $this->attempts($kernel));

        $calls = $this->service($kernel, LockingWriteTool::class)->calls;
        self::assertNotSame($calls[0], $calls[1], 'each attempt gets a fresh call');
        self::assertSame($calls[0]->context, $calls[1]->context);

        self::assertSame(['note_lock'], $this->service($kernel, FixtureRateLimiter::class)->writes);
        $events = $this->service($kernel, AuditRecorder::class)->events;
        self::assertCount(1, $events);
        self::assertSame(McpCallOutcome::Ok, $events[0]->outcome);
        self::assertSame(['note' => 'n-1'], $events[0]->auditIds);
        self::assertSame(1, $this->unit($kernel)->units);
    }

    #[Test]
    public function aToolThatKeepsConflictingAnswersAJsonRpcErrorWithRetryInfo(): void
    {
        $kernel   = $this->boot();
        $response = $this->lock($kernel, 'always');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('1', $response->headers->get('Retry-After'));
        $error = $this->json($response)['error'];
        self::assertSame(McpProtocol::WRITE_CONFLICT, $error['code']);
        self::assertSame('Write conflict; retry later', $error['message']);
        self::assertSame(['code' => 'CONFLICT_001', 'retry_after' => 1, 'committed' => false], $error['data']);
        self::assertSame([1, 2, 3], $this->attempts($kernel));

        self::assertSame(['note_lock'], $this->service($kernel, FixtureRateLimiter::class)->writes);
        $events = $this->service($kernel, AuditRecorder::class)->events;
        self::assertCount(1, $events);
        self::assertSame(McpCallOutcome::Conflict, $events[0]->outcome);
        self::assertSame('CONFLICT_001', $events[0]->ecode);
    }

    #[Test]
    public function theLegacyEraGetsTheSameConflictAnswer(): void
    {
        $kernel   = $this->boot();
        $response = $this->legacy($kernel, 'tools/call', ['name' => 'note_lock', 'arguments' => ['mode' => 'always']], [], '2025-11-25', 'tok-grace');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('CONFLICT_001', $this->json($response)['error']['data']['code']);
    }

    #[Test]
    public function aCommittedThenConflictCaseAnswersConflict002WithoutRetry(): void
    {
        $kernel   = $this->boot();
        $response = $this->lock($kernel, 'committed');

        self::assertSame(409, $response->getStatusCode());
        self::assertFalse($response->headers->has('Retry-After'));
        $error = $this->json($response)['error'];
        self::assertSame(McpProtocol::WRITE_CONFLICT, $error['code']);
        self::assertSame(['code' => 'CONFLICT_002', 'committed' => true], $error['data']);
        self::assertSame([1], $this->attempts($kernel), 'a unit that already committed is never replayed');

        $events = $this->service($kernel, AuditRecorder::class)->events;
        self::assertCount(1, $events);
        self::assertSame(McpCallOutcome::Conflict, $events[0]->outcome);
        self::assertSame('CONFLICT_002', $events[0]->ecode);
    }

    #[Test]
    public function withoutTheConflictPolicyTheAnswerIs409WithTheUnitsHint(): void
    {
        $kernel   = $this->boot(true, false);
        $response = $this->lock($kernel, 'always');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('1', $response->headers->get('Retry-After'));
    }

    #[Test]
    public function readToolsAreNotWrapped(): void
    {
        $kernel   = $this->boot();
        $response = $this->modern($kernel, 'tools/call', ['name' => 'note_get', 'arguments' => ['id' => NoteGetTool::ID]], [], 'tok-grace');

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($this->json($response)['result']['isError']);
        self::assertSame(0, $this->unit($kernel)->units);
        self::assertSame([], $this->service($kernel, FixtureRateLimiter::class)->writes);
    }

    #[Test]
    public function withTheOptionOffBehaviourIsUnchanged(): void
    {
        $kernel = $this->boot(false, false);
        self::assertFalse($kernel->getContainer()->has(TransactionDoctrineWriteRunner::class));

        $response = $this->lock($kernel, 'once');

        // As in 1.1: the tool runs once and its database failure is an internal error.
        self::assertSame(500, $response->getStatusCode());
        self::assertSame(McpProtocol::INTERNAL_ERROR, $this->json($response)['error']['code']);
        self::assertSame([1], $this->attempts($kernel));
        $events = $this->service($kernel, AuditRecorder::class)->events;
        self::assertCount(1, $events);
        self::assertSame(McpCallOutcome::InternalError, $events[0]->outcome);
    }

    #[Test]
    public function workerModeKeepsNothingBetweenCalls(): void
    {
        // One kernel, never reset (FrankenPHP worker, kernel reset off): every call starts again at attempt 1 and counts
        // its own budget hit and audit event.
        $kernel = $this->boot();
        $tool   = $this->service($kernel, LockingWriteTool::class);

        for ($i = 0; $i < 60; ++$i) {
            $tool->calls = [];
            $once        = $this->lock($kernel, 'once', $i);
            self::assertSame(200, $once->getStatusCode());
            self::assertSame([1, 2], $this->attempts($kernel));

            $tool->calls = [];
            $always      = $this->lock($kernel, 'always', $i);
            self::assertSame(409, $always->getStatusCode());
            self::assertSame([1, 2, 3], $this->attempts($kernel));

            $read = $this->modern($kernel, 'tools/call', ['name' => 'note_get', 'arguments' => ['id' => NoteGetTool::ID]], [], 'tok-ada', $i);
            self::assertSame(200, $read->getStatusCode());
        }

        self::assertCount(120, $this->service($kernel, FixtureRateLimiter::class)->writes);
        self::assertSame(120, $this->unit($kernel)->units);
        $outcomes = array_map(static fn (McpToolCallEvent $event): McpCallOutcome => $event->outcome, $this->service($kernel, AuditRecorder::class)->events);
        self::assertCount(180, $outcomes);
        self::assertSame([McpCallOutcome::Ok, McpCallOutcome::Conflict, McpCallOutcome::Ok], [$outcomes[0], $outcomes[1], $outcomes[2]]);
    }
}

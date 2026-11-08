<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Integration\Kernel;

use Nowo\McpKitBundle\Context\McpCallContext;
use Nowo\McpKitBundle\DependencyInjection\Compiler\McpRegistryPass;
use Nowo\McpKitBundle\Envelope\McpEnvelope;
use Nowo\McpKitBundle\Event\McpCallOutcome;
use Nowo\McpKitBundle\Event\McpToolCallEvent;
use Nowo\McpKitBundle\Protocol\McpProtocol;
use Nowo\McpKitBundle\Registry\McpToolRegistry;
use Nowo\McpKitBundle\Schema\McpSchemaNormalizer;
use Nowo\McpKitBundle\Schema\McpSchemaValidator;
use Nowo\McpKitBundle\Server\McpDispatcher;
use Nowo\McpKitBundle\Server\McpToolInvoker;
use Nowo\McpKitBundle\Tests\Fixtures\AuditRecorder;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureAuthorizer;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureRateLimiter;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\MisbehavingTool;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteArchiveTool;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteCreateTool;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteGetTool;
use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

use function array_column;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * `tools/list` and `tools/call` through the whole kernel.
 */
#[CoversClass(McpToolInvoker::class)]
#[CoversClass(McpToolRegistry::class)]
#[CoversClass(McpRegistryPass::class)]
#[CoversClass(McpDispatcher::class)]
#[CoversClass(McpEnvelope::class)]
#[CoversClass(McpSchemaValidator::class)]
#[CoversClass(McpSchemaNormalizer::class)]
#[CoversClass(McpToolCallEvent::class)]
#[CoversClass(McpToolCall::class)]
#[CoversClass(McpToolResult::class)]
#[CoversClass(McpToolDefinition::class)]
final class ToolsTest extends KernelTestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function boot(array $config = [], bool $withAudit = true): McpKitTestKernel
    {
        $services = [
            NoteGetTool::class       => NoteGetTool::class,
            NoteCreateTool::class    => NoteCreateTool::class,
            NoteArchiveTool::class   => NoteArchiveTool::class,
            MisbehavingTool::class   => MisbehavingTool::class,
            FixtureAuthorizer::class => FixtureAuthorizer::class,
        ];
        if ($withAudit) {
            $services[AuditRecorder::class] = AuditRecorder::class;
        }

        return $this->bootKernel(array_replace_recursive(['hooks' => ['authorizer' => FixtureAuthorizer::class]], $config), $services);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function callTool(McpKitTestKernel $kernel, string $name, array $arguments, string $token = 'tok-grace'): array
    {
        $response = $this->modern($kernel, 'tools/call', ['name' => $name, 'arguments' => $arguments === [] ? new stdClass() : $arguments], [], $token);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $this->json($response)['result'];
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function errorBody(array $result): array
    {
        self::assertTrue($result['isError']);
        $decoded = json_decode($result['content'][0]['text'], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return list<McpToolCallEvent>
     */
    private function events(McpKitTestKernel $kernel): array
    {
        return $this->service($kernel, AuditRecorder::class)->events;
    }

    #[Test]
    public function listShowsWhatTheTokenMayUseInAFixedOrder(): void
    {
        $kernel = $this->boot();

        $ada = $this->json($this->modern($kernel, 'tools/list', [], [], 'tok-ada'))['result']['tools'];
        self::assertSame(['note_get', 'misbehave'], array_column($ada, 'name'));

        $grace = $this->json($this->modern($kernel, 'tools/list', [], [], 'tok-grace'))['result']['tools'];
        self::assertSame(['note_get', 'misbehave', 'note_archive', 'note_create'], array_column($grace, 'name'));

        $create = $grace[3];
        self::assertSame('Create a note', $create['title']);
        self::assertSame(['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => false], $create['annotations']);
        self::assertSame(['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true, 'openWorldHint' => false], $grace[2]['annotations']);
        self::assertSame(['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false], $grace[0]['annotations']);
        self::assertFalse($create['inputSchema']['additionalProperties']);
    }

    #[Test]
    public function destructiveToolsNeedTheTickEvenWhenTheAuthorizerAllows(): void
    {
        $kernel                                                            = $this->boot();
        $this->service($kernel, FixtureAuthorizer::class)->allowEverything = true;

        $tools = $this->json($this->modern($kernel, 'tools/list', [], [], 'tok-ada'))['result']['tools'];
        self::assertNotContains('note_archive', array_column($tools, 'name'));

        $result = $this->callTool($kernel, 'note_archive', ['id' => NoteGetTool::ID], 'tok-ada');
        self::assertSame(['ECODE' => 'TOKEN_001', 'err' => 'This token may not do this'], $this->errorBody($result));
        self::assertSame(McpCallOutcome::Refused, $this->events($kernel)[0]->outcome);
    }

    #[Test]
    public function anUnlistedToolRefusesWithTokenOne(): void
    {
        $kernel = $this->boot();

        self::assertSame('TOKEN_001', $this->errorBody($this->callTool($kernel, 'note_create', ['title' => 'x'], 'tok-ada'))['ECODE']);
    }

    #[Test]
    public function aReadReturnsTheUntrustedEnvelope(): void
    {
        $kernel = $this->boot();
        $result = $this->callTool($kernel, 'note_get', ['id' => NoteGetTool::ID]);

        self::assertFalse($result['isError']);
        self::assertSame(['type' => 'text', 'text' => McpProtocol::UNTRUSTED_NOTICE], $result['content'][0]);
        self::assertSame(['id' => NoteGetTool::ID, 'untrusted' => ['title' => 'Runbook', 'body' => NoteGetTool::INJECTION]], $result['structuredContent']);
        self::assertSame(['type' => 'resource_link', 'uri' => 'demo://note/' . NoteGetTool::ID, 'name' => 'note'], $result['content'][2]);

        $text = $result['content'][1]['text'];
        self::assertSame($result['structuredContent'], json_decode($text, true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('</untrusted>', $text);
        self::assertStringNotContainsString("\u{202E}", $text);
        self::assertStringNotContainsString("\u{200B}", $text);
        self::assertStringNotContainsString("\u{E0041}", $text);
        self::assertStringContainsString('\\u202e', $text);
        self::assertStringContainsString('\\udb40\\udc41', $text);
        self::assertSame('complete', $this->json($this->modern($kernel, 'tools/call', ['name' => 'note_get', 'arguments' => ['id' => NoteGetTool::ID]], [], 'tok-ada'))['result']['resultType']);

        $event = $this->events($kernel)[0];
        self::assertSame(McpCallOutcome::Ok, $event->outcome);
        self::assertSame(['note' => NoteGetTool::ID], $event->auditIds);
        self::assertSame('test-client', $event->context->client?->name);
        self::assertSame('note_get', $event->context->toolName);
    }

    #[Test]
    public function businessErrorsAreToolResults(): void
    {
        $kernel = $this->boot();

        self::assertSame(['ECODE' => 'ACCESS_001', 'err' => 'Not found'], $this->errorBody($this->callTool($kernel, 'note_get', ['id' => '0b8f3c5e-6a1d-4c7e-9f2a-1b3c5d7e9f02'])));
        self::assertSame(['ECODE' => 'WIKI_409', 'err' => 'Stale revision', 'current_revision' => 13], $this->errorBody($this->callTool($kernel, 'note_create', ['title' => 'conflict'])));
        self::assertSame(McpCallOutcome::ToolError, $this->events($kernel)[1]->outcome);
        self::assertSame('WIKI_409', $this->events($kernel)[1]->ecode);
    }

    #[Test]
    public function argumentsAreValidated(): void
    {
        $kernel = $this->boot();

        self::assertSame(['ECODE' => 'INPUT_001', 'err' => 'Unknown argument: colour'], $this->errorBody($this->callTool($kernel, 'note_create', ['title' => 'x', 'colour' => 'red'])));
        self::assertSame('Unknown argument', $this->errorBody($this->callTool($kernel, 'note_create', ['title' => 'x', 'Évil key' => 1]))['err']);
        self::assertSame('Invalid field: title', $this->errorBody($this->callTool($kernel, 'note_create', ['title' => 5]))['err']);
        self::assertSame('Invalid field: title', $this->errorBody($this->callTool($kernel, 'note_create', []))['err']);
        self::assertSame('Invalid field: tags.1', $this->errorBody($this->callTool($kernel, 'note_create', ['title' => 'x', 'tags' => ['a', 7]]))['err']);
        self::assertSame('Invalid field: meta', $this->errorBody($this->callTool($kernel, 'note_create', ['title' => 'x', 'meta' => ['bad key!' => true]]))['err']);
        self::assertSame('Invalid field: id', $this->errorBody($this->callTool($kernel, 'note_get', ['id' => 'not-a-uuid']))['err']);
        self::assertSame(McpCallOutcome::InvalidInput, $this->events($kernel)[0]->outcome);
        self::assertSame([], $this->service($kernel, FixtureRateLimiter::class)->writes);
        self::assertSame([], $this->service($kernel, NoteCreateTool::class)->calls);
    }

    #[Test]
    public function writesPassTheWriteBudgetAndCarryTheIdempotencyKey(): void
    {
        $kernel = $this->boot();
        $result = $this->callTool($kernel, 'note_create', ['title' => 'x', 'idempotency_key' => 'k-1']);

        self::assertFalse($result['isError']);
        self::assertSame(['note_create'], $this->service($kernel, FixtureRateLimiter::class)->writes);
        $call = $this->service($kernel, NoteCreateTool::class)->calls[0];
        self::assertSame('k-1', $call->idempotencyKey());
        self::assertSame('note_create', McpCallContext::fromRequest($call->request)?->toolName);
        self::assertSame(['title'], $this->events($kernel)[0]->changedFields);
        self::assertSame('Invalid field: idempotency_key', $this->errorBody($this->callTool($kernel, 'note_create', ['title' => 'x', 'idempotency_key' => 'bad key']))['err']);
    }

    #[Test]
    public function aRefusedWriteBudgetAnswers429BeforeTheTool(): void
    {
        $kernel                                                          = $this->boot();
        $this->service($kernel, FixtureRateLimiter::class)->refuseWrites = true;

        $response = $this->modern($kernel, 'tools/call', ['name' => 'note_create', 'arguments' => ['title' => 'x']], [], 'tok-grace');
        $this->assertRpcError($response, 429, -31001);
        self::assertSame(['limit' => 'writes'], $this->json($response)['error']['data']);
        self::assertNotNull($response->headers->get('Retry-After'));
        self::assertSame([], $this->service($kernel, NoteCreateTool::class)->calls);
        self::assertSame(McpCallOutcome::RateLimited, $this->events($kernel)[0]->outcome);
        // Reads do not touch the write budget.
        self::assertFalse($this->callTool($kernel, 'note_get', ['id' => NoteGetTool::ID])['isError']);
    }

    #[Test]
    public function writesAreRefusedWithoutAnAuditListener(): void
    {
        $kernel = $this->boot([], false);

        self::assertSame(['ECODE' => 'AUDIT_001', 'err' => 'Audit is not configured'], $this->errorBody($this->callTool($kernel, 'note_create', ['title' => 'x'])));
        self::assertSame([], $this->service($kernel, NoteCreateTool::class)->calls);
        self::assertFalse($this->callTool($kernel, 'note_get', ['id' => NoteGetTool::ID])['isError']);
    }

    #[Test]
    public function aThrowingListenerDoesNotHideAWrite(): void
    {
        $kernel                                              = $this->boot();
        $this->service($kernel, AuditRecorder::class)->throw = true;

        self::assertFalse($this->callTool($kernel, 'note_create', ['title' => 'x'])['isError']);
        self::assertCount(1, $this->service($kernel, NoteCreateTool::class)->calls);
    }

    #[Test]
    public function protocolErrors(): void
    {
        $kernel = $this->boot();

        $this->assertRpcError($this->modern($kernel, 'tools/call', ['name' => 'nope'], [], 'tok-grace'), 200, -32602);
        $this->assertRpcError($this->modern($kernel, 'tools/call', ['name' => 'note_get', 'arguments' => [1, 2]], [], 'tok-grace'), 400, -32602);
        self::assertSame([], $this->events($kernel));
    }

    #[Test]
    public function brokenToolsAnswerInternalErrorsWithoutDetail(): void
    {
        $kernel = $this->boot();

        foreach (['schema', 'throw', 'object', 'misplaced'] as $mode) {
            $response = $this->modern($kernel, 'tools/call', ['name' => 'misbehave', 'arguments' => ['mode' => $mode]], [], 'tok-grace');
            $this->assertRpcError($response, 500, -32603);
            self::assertStringNotContainsString('bt_tool_leak', (string) $response->getContent());
        }
        self::assertSame(McpCallOutcome::InternalError, $this->events($kernel)[0]->outcome);
    }

    #[Test]
    public function emptyObjectsStayObjects(): void
    {
        $kernel = $this->boot();
        $result = $this->callTool($kernel, 'misbehave', ['mode' => 'empty']);

        self::assertFalse($result['isError']);
        self::assertStringContainsString('"structuredContent":{"items":{},"untrusted":{}}', (string) $this->modern($kernel, 'tools/call', ['name' => 'misbehave', 'arguments' => ['mode' => 'empty']], [], 'tok-grace')->getContent());
    }

    #[Test]
    public function oversizedResultsAreRefused(): void
    {
        $kernel = $this->boot(['limits' => ['max_result_bytes' => 2048]]);

        self::assertSame(['ECODE' => 'OUTPUT_001', 'err' => 'Result too large; narrow the request'], $this->errorBody($this->callTool($kernel, 'misbehave', ['mode' => 'big'])));
    }

    #[Test]
    public function slowCallsAreLogged(): void
    {
        $kernel = $this->boot(['limits' => ['slow_call_ms' => 0]]);
        $this->service($kernel, AuditRecorder::class);

        self::assertFalse($this->callTool($kernel, 'note_get', ['id' => NoteGetTool::ID])['isError']);
    }

    #[Test]
    public function legacyCallsHaveNoModernFields(): void
    {
        $kernel = $this->boot();
        $result = $this->json($this->legacy($kernel, 'tools/call', ['name' => 'note_get', 'arguments' => ['id' => NoteGetTool::ID]], [], '2025-06-18', 'tok-ada'))['result'];

        self::assertArrayNotHasKey('resultType', $result);
        self::assertFalse($result['isError']);
        self::assertNull($this->events($kernel)[0]->context->client);
    }
}

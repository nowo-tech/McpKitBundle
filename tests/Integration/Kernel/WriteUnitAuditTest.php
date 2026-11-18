<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Integration\Kernel;

use Nowo\McpKitBundle\Event\McpToolCallEvent;
use Nowo\McpKitBundle\Security\UserProviderPrincipalRefresher;
use Nowo\McpKitBundle\Server\McpToolInvoker;
use Nowo\McpKitBundle\Tests\Fixtures\AuditRecorder;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureAuthorizer;
use Nowo\McpKitBundle\Tests\Fixtures\Orm\FailingPrincipalRefresher;
use Nowo\McpKitBundle\Tests\Fixtures\Orm\FixtureOrm;
use Nowo\McpKitBundle\Tests\Fixtures\Orm\HostContextRefresher;
use Nowo\McpKitBundle\Tests\Fixtures\Orm\OrmAuditListener;
use Nowo\McpKitBundle\Tests\Fixtures\Orm\OrmManagerRegistry;
use Nowo\McpKitBundle\Tests\Fixtures\Orm\OrmMcpAuthenticator;
use Nowo\McpKitBundle\Tests\Fixtures\Orm\OrmUser;
use Nowo\McpKitBundle\Tests\Fixtures\Orm\OrmUserProvider;
use Nowo\McpKitBundle\Tests\Fixtures\Orm\OrmWriteUnit;
use Nowo\McpKitBundle\Tests\Fixtures\TestFirewallAuthenticator;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\LockingWriteTool;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\WriteUnit\WriteUnitInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

use function count;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Audit SZ-M1: transaction-doctrine clears the host entity manager between attempts and before a committed conflict,
 * which detaches the principal's user. A host audit listener that links that user (flush mode "host") must still write
 * its row for a replayed call and for `CONFLICT_002`, and must leave nothing queued in the shared manager.
 */
#[CoversClass(McpToolInvoker::class)]
#[CoversClass(UserProviderPrincipalRefresher::class)]
#[CoversClass(McpToolCallEvent::class)]
final class WriteUnitAuditTest extends KernelTestCase
{
    private function boot(?string $refresher = null): McpKitTestKernel
    {
        $services = [
            FixtureOrm::class                => FixtureOrm::class,
            OrmManagerRegistry::class        => OrmManagerRegistry::class,
            WriteUnitInterface::class        => OrmWriteUnit::class,
            ConflictResponsePolicy::class    => ConflictResponsePolicy::class,
            OrmMcpAuthenticator::class       => OrmMcpAuthenticator::class,
            OrmUserProvider::class           => OrmUserProvider::class,
            OrmAuditListener::class          => OrmAuditListener::class,
            AuditRecorder::class             => AuditRecorder::class,
            LockingWriteTool::class          => LockingWriteTool::class,
            FixtureAuthorizer::class         => FixtureAuthorizer::class,
            FailingPrincipalRefresher::class => FailingPrincipalRefresher::class,
            HostContextRefresher::class      => HostContextRefresher::class,
        ];
        $security = [
            'providers' => [
                'memory' => ['memory' => ['users' => ['grace' => ['password' => '', 'roles' => ['ROLE_USER']]]]],
                'orm'    => ['id' => OrmUserProvider::class],
            ],
            'firewalls' => ['mcp' => ['pattern' => '^/mcp$', 'stateless' => true, 'provider' => 'memory', 'custom_authenticators' => [TestFirewallAuthenticator::class]]],
        ];

        return $this->bootKernel([
            'hooks'      => ['authenticator' => OrmMcpAuthenticator::class, 'authorizer' => FixtureAuthorizer::class, 'principal_refresher' => $refresher],
            'write_unit' => ['enabled' => true],
        ], $services, $security);
    }

    private function lock(McpKitTestKernel $kernel, string $mode, int $id = 1): Response
    {
        return $this->modern($kernel, 'tools/call', ['name' => 'note_lock', 'arguments' => ['mode' => $mode]], [], 'tok-grace', $id);
    }

    private function orm(McpKitTestKernel $kernel): FixtureOrm
    {
        return $this->service($kernel, FixtureOrm::class);
    }

    private function lastEvent(McpKitTestKernel $kernel): McpToolCallEvent
    {
        $events = $this->service($kernel, AuditRecorder::class)->events;
        self::assertNotSame([], $events);

        return $events[count($events) - 1];
    }

    #[Test]
    public function aReplayedCallWritesItsAuditRowWithAManagedUser(): void
    {
        $kernel   = $this->boot();
        $response = $this->lock($kernel, 'once');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([['note_lock', 'ok', 2]], $this->orm($kernel)->auditRows());
        self::assertSame([], $this->service($kernel, OrmAuditListener::class)->failures);
        self::assertFalse($this->orm($kernel)->hasQueuedWork());

        $event = $this->lastEvent($kernel);
        self::assertSame(2, $event->attempts);
        self::assertTrue($event->replayed);
        self::assertInstanceOf(OrmUser::class, $event->principal->user);
        self::assertTrue($this->orm($kernel)->em->contains($event->principal->user), 'the refreshed user is managed');
        self::assertSame('id-grace', $event->principal->tokenId);
    }

    #[Test]
    public function aCommittedConflictWritesItsAuditRow(): void
    {
        $kernel   = $this->boot();
        $response = $this->lock($kernel, 'committed');

        self::assertSame(200, $response->getStatusCode());
        $result = $this->json($response)['result'];
        self::assertTrue($result['isError']);
        self::assertSame('CONFLICT_002', json_decode($result['content'][0]['text'], true, 512, JSON_THROW_ON_ERROR)['ECODE']);

        self::assertSame([['note_lock', 'conflict', 1]], $this->orm($kernel)->auditRows());
        self::assertSame([], $this->service($kernel, OrmAuditListener::class)->failures);
        self::assertFalse($this->orm($kernel)->hasQueuedWork());
        self::assertFalse($this->lastEvent($kernel)->replayed);
    }

    #[Test]
    public function aRetryableConflictWritesItsAuditRow(): void
    {
        $kernel   = $this->boot();
        $response = $this->lock($kernel, 'always');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame([['note_lock', 'conflict', 3]], $this->orm($kernel)->auditRows());
        self::assertFalse($this->orm($kernel)->hasQueuedWork());
    }

    #[Test]
    public function workerModeNeverLeavesAStaleRowQueued(): void
    {
        // One kernel, never reset: the shared entity manager must stay clean after every call.
        $kernel = $this->boot();
        for ($i = 0; $i < 20; ++$i) {
            self::assertSame(200, $this->lock($kernel, 'once', $i)->getStatusCode());
            self::assertFalse($this->orm($kernel)->hasQueuedWork());
            self::assertSame(200, $this->lock($kernel, 'committed', $i)->getStatusCode());
            self::assertFalse($this->orm($kernel)->hasQueuedWork());
        }
        self::assertCount(40, $this->orm($kernel)->auditRows());
        self::assertSame([], $this->service($kernel, OrmAuditListener::class)->failures);
    }

    #[Test]
    public function theHostRefresherWinsOverTheUserProvider(): void
    {
        $kernel = $this->boot(HostContextRefresher::class);
        self::assertSame(200, $this->lock($kernel, 'once')->getStatusCode());

        $context = $this->lastEvent($kernel)->principal->hostContext;
        self::assertInstanceOf(stdClass::class, $context);
        self::assertTrue($context->refreshed);
        self::assertSame([['note_lock', 'ok', 2]], $this->orm($kernel)->auditRows());
    }

    #[Test]
    public function aFailedRefreshStillDispatchesTheEventWithTheOriginalPrincipal(): void
    {
        $kernel = $this->boot(FailingPrincipalRefresher::class);
        self::assertSame(200, $this->lock($kernel, 'once')->getStatusCode());

        $events = $this->service($kernel, AuditRecorder::class)->events;
        self::assertCount(1, $events, 'the event is never dropped');
        self::assertSame('id-grace', $events[0]->principal->tokenId);
        self::assertFalse($this->orm($kernel)->em->contains($events[0]->principal->user), 'the original, detached user');

        // What is left over: a listener that links the detached entity fails, and in host flush mode its row stays queued
        // in the shared manager. Id-based recording (AuditKit recordIdsAndFlush()) survives this path (SZ-L3).
        self::assertSame([], $this->orm($kernel)->auditRows());
        self::assertNotSame([], $this->service($kernel, OrmAuditListener::class)->failures);
        self::assertTrue($this->orm($kernel)->hasQueuedWork());
        $this->orm($kernel)->em->clear();
    }
}

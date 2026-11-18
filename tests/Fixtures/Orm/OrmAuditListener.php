<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Orm;

use Nowo\McpKitBundle\Event\McpToolCallEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Throwable;

/**
 * A host audit listener in "host" flush mode: it links the principal's user entity (no cascade) and flushes the shared
 * entity manager. With a detached user, flush() throws and the row stays queued. Runs after AuditRecorder.
 */
#[AsEventListener(priority: -10)]
final class OrmAuditListener
{
    /** @var list<class-string<Throwable>> */
    public array $failures = [];

    public function __construct(
        private readonly FixtureOrm $orm,
    ) {
    }

    public function __invoke(McpToolCallEvent $event): void
    {
        $user = $event->principal->user;
        if (!$user instanceof OrmUser) {
            return;
        }
        try {
            $this->orm->em->persist(new OrmAuditRow($user, $event->name, $event->outcome->value, $event->attempts));
            $this->orm->em->flush();
        } catch (Throwable $throwable) {
            $this->failures[] = $throwable::class;

            throw $throwable;
        }
    }
}

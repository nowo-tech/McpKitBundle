<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures;

use Nowo\McpKitBundle\Event\McpToolCallEvent;
use RuntimeException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Records audit events; can be told to throw.
 */
#[AsEventListener]
final class AuditRecorder
{
    /** @var list<McpToolCallEvent> */
    public array $events = [];

    public bool $throw = false;

    public function __invoke(McpToolCallEvent $event): void
    {
        $this->events[] = $event;
        if ($this->throw) {
            throw new RuntimeException('listener down');
        }
    }
}

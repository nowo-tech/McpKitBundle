<?php

declare(strict_types=1);

namespace App\Mcp;

use Nowo\McpKitBundle\Event\McpToolCallEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Demo audit: one log line per call with token, tool, outcome and ids (never arguments or text).
 */
#[AsEventListener]
final readonly class DemoAuditListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(McpToolCallEvent $event): void
    {
        $this->logger->info('MCP call', [
            'token'     => $event->principal->tokenId,
            'tool'      => $event->name,
            'outcome'   => $event->outcome->value,
            'ecode'     => $event->ecode,
            'ids'       => $event->auditIds,
            'client'    => $event->context->client?->name ?? $event->context->userAgent ?? 'unknown client',
            'duration'  => $event->durationMs,
        ]);
    }
}

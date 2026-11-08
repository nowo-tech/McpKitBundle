<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Event;

use Nowo\McpKitBundle\Context\McpCallContext;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after every `tools/call` (and `resources/read`) that reaches the registry, refusals included, for the
 * host's audit. It carries ids and field names only: never arguments, results, text or a secret. Without a listener,
 * additive and destructive tools are refused (`AUDIT_001`).
 */
final class McpToolCallEvent extends Event
{
    /**
     * Creates the event.
     *
     * @param McpPrincipal $principal the principal (no secret)
     * @param string $name the tool or resource template name
     * @param McpToolKind $kind the tool kind (`ReadOnly` for resources)
     * @param McpCallOutcome $outcome how the call ended
     * @param string|null $ecode the `ECODE` of a tool error or refusal
     * @param array<string, string> $auditIds ids the call read or changed
     * @param list<string> $changedFields names of changed fields
     * @param McpCallContext $context era, version, client as reported, User-Agent
     * @param int $durationMs duration in milliseconds
     */
    public function __construct(
        public readonly McpPrincipal $principal,
        public readonly string $name,
        public readonly McpToolKind $kind,
        public readonly McpCallOutcome $outcome,
        public readonly ?string $ecode,
        public readonly array $auditIds,
        public readonly array $changedFields,
        public readonly McpCallContext $context,
        public readonly int $durationMs,
    ) {
    }
}

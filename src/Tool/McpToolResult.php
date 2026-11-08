<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tool;

/**
 * A successful tool result: the data (with {@see McpUntrusted} groups under `untrusted` keys) and what the audit event
 * carries (ids and field names only).
 */
final readonly class McpToolResult
{
    /**
     * Creates the result.
     *
     * @param array<string, mixed> $data the structured data
     * @param array<string, string> $auditIds ids the call read or changed
     * @param list<string> $changedFields names of changed fields (never values)
     * @param list<McpResourceLink> $resourceLinks links to resources
     */
    private function __construct(
        public array $data,
        public array $auditIds,
        public array $changedFields,
        public array $resourceLinks,
    ) {
    }

    /**
     * A successful result.
     *
     * @param array<string, mixed> $data the structured data
     * @param array<string, string> $auditIds ids the call read or changed
     * @param list<string> $changedFields names of changed fields
     * @param list<McpResourceLink> $resourceLinks links to resources
     *
     * @return self the result
     */
    public static function ok(array $data, array $auditIds = [], array $changedFields = [], array $resourceLinks = []): self
    {
        return new self($data, $auditIds, $changedFields, $resourceLinks);
    }
}

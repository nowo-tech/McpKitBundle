<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tool;

use Nowo\McpKitBundle\Schema\McpSchemaRules;

/**
 * A tool as listed by `tools/list`: static data from code, validated when the container is built
 * ({@see McpSchemaRules}).
 */
final readonly class McpToolDefinition
{
    /**
     * Creates the definition.
     *
     * @param string $name `^[a-z][a-z0-9_]{0,63}$`, unique
     * @param string $title display title
     * @param string $description static English, at most 2,000 characters; never user content
     * @param McpToolKind $kind read-only, additive or destructive
     * @param bool $idempotent whether calling twice with the same arguments has the effect of calling once
     * @param array<string, mixed> $inputSchema JSON Schema 2020-12, root `type: object`, `additionalProperties: false`
     * @param array<string, mixed> $outputSchema JSON Schema 2020-12 of `structuredContent`
     * @param list<string> $requires opaque grants for the host's authorizer, e.g. `['tasks:my_tasks']`
     * @param bool $openWorld `openWorldHint`
     * @param int $priority list order: priority descending, then name
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $description,
        public McpToolKind $kind,
        public bool $idempotent,
        public array $inputSchema,
        public array $outputSchema,
        public array $requires = [],
        public bool $openWorld = false,
        public int $priority = 0,
    ) {
    }

    /**
     * The annotations, always explicit and derived from the kind.
     *
     * @return array{readOnlyHint: bool, destructiveHint: bool, idempotentHint: bool, openWorldHint: bool}
     */
    public function annotations(): array
    {
        return [
            'readOnlyHint'    => $this->kind === McpToolKind::ReadOnly,
            'destructiveHint' => $this->kind === McpToolKind::Destructive,
            'idempotentHint'  => $this->kind === McpToolKind::ReadOnly || $this->idempotent,
            'openWorldHint'   => $this->openWorld,
        ];
    }
}

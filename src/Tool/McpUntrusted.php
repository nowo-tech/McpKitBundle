<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tool;

/**
 * Values written by people (titles, Markdown, comments, names…). Put it as the value of an `untrusted` key of a tool's
 * data: the envelope emits it as an `untrusted` object and adds the fixed notice before the result.
 */
final readonly class McpUntrusted
{
    /**
     * Creates the group.
     *
     * @param array<string, mixed> $fields the untrusted fields
     */
    private function __construct(
        public array $fields,
    ) {
    }

    /**
     * Marks fields as untrusted.
     *
     * @param array<string, mixed> $fields the fields
     *
     * @return self the group
     */
    public static function of(array $fields): self
    {
        return new self($fields);
    }
}

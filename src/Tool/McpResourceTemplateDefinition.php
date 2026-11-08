<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tool;

use function preg_quote;
use function preg_replace_callback;

/**
 * A resource template as listed by `resources/templates/list`: static data from code, validated when the container is
 * built. URIs follow RFC 6570 level 1 (`demo://note/{id}`); each variable has its own regular expression.
 */
final readonly class McpResourceTemplateDefinition
{
    /**
     * Creates the definition.
     *
     * @param string $name `^[a-z][a-z0-9_]{0,63}$`, unique among templates
     * @param string $uriTemplate e.g. `beacon://task/{id}`
     * @param string $title display title
     * @param string $description static English, at most 2,000 characters
     * @param array<string, string> $variables variable name => regular expression body (no delimiters, no anchors)
     * @param array<string, mixed> $outputSchema JSON Schema 2020-12 of the contents' JSON
     * @param list<string> $requires opaque grants for the host's authorizer
     * @param int $priority match and list order: priority descending, then name
     */
    public function __construct(
        public string $name,
        public string $uriTemplate,
        public string $title,
        public string $description,
        public array $variables,
        public array $outputSchema,
        public array $requires = [],
        public int $priority = 0,
    ) {
    }

    /**
     * The full-match regular expression of the template, with one named group per variable.
     *
     * @return string the pattern
     */
    public function pattern(): string
    {
        $quoted = preg_quote($this->uriTemplate, '#');
        $body   = (string) preg_replace_callback('/\\\\\{([a-z_][a-z0-9_]*)\\\\\}/', fn (array $match): string => '(?P<' . $match[1] . '>' . ($this->variables[$match[1]] ?? '(?!)') . ')', $quoted);

        return '#^' . $body . '$#Du';
    }
}

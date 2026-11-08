<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Schema;

use LogicException;
use Nowo\McpKitBundle\Tool\McpResourceTemplateDefinition;
use Nowo\McpKitBundle\Tool\McpToolDefinition;

use function array_diff;
use function array_filter;
use function array_intersect;
use function array_is_list;
use function array_keys;
use function array_unique;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function mb_strlen;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Compile-time rules for tool definitions. A violation stops the container build, so a tool cannot ship with a schema
 * the kit cannot guarantee.
 *
 * Input schemas: root `type: object` with `additionalProperties: false`; every object closed, every string with
 * `maxLength`, every array with `maxItems` and `items`. Output schemas: every object closed, and a free-text string
 * may only live under an `untrusted` object (outside it, a string needs `format`, `enum`, `const` or `pattern`). Both:
 * a keyword whitelist (no `x-mcp-header`), local `$ref` only, depth ≤ 8, `oneOf` ≤ 8 branches.
 */
final class McpSchemaRules
{
    public const NAME_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

    private const MAX_DEPTH = 8;

    private const MAX_BRANCHES = 8;

    private const KEYWORDS = [
        'type', 'properties', 'required', 'additionalProperties', 'items', 'enum', 'const', 'minimum', 'maximum',
        'exclusiveMinimum', 'exclusiveMaximum', 'minLength', 'maxLength', 'pattern', 'format', 'minItems', 'maxItems',
        'uniqueItems', 'description', 'title', 'default', 'oneOf', '$defs', '$ref',
    ];

    private const TYPES = ['object', 'array', 'string', 'integer', 'number', 'boolean', 'null'];

    /** Formats that cannot carry free text. */
    private const TRUSTED_FORMATS = ['uuid', 'date', 'date-time'];

    /** Longest trusted string with a `pattern` (cursors fit). */
    private const MAX_PATTERNED_LENGTH = 512;

    /** Longest trusted `format: uri` string. */
    private const MAX_URI_LENGTH = 2048;

    /**
     * One token of a safe trusted pattern body: a literal (letters, digits, `_ : # @ / -`), an escaped literal, `\d`, a
     * positive character class of those characters and same-class ranges (`a-z`, `A-Z`, `0-9`; a `.` is literal inside a
     * class), a quantifier, a group or an alternation inside a group. No `.` wildcard, no whitespace, no `\s \S \w \W
     * \p`, no negated class, no top-level alternation (it would break the anchors).
     */
    private const SAFE_PATTERN_TOKEN = '~\G(?:[A-Za-z0-9_:#@/-]|\\\\[.\-/#:+?*()\[\]{}^$|]|\\\\d|\[(?:[a-z]-[a-z]|[A-Z]-[A-Z]|[0-9]-[0-9]|[A-Za-z0-9_:#@/.]|-(?=\])|\\\\[.\-/])+\]|\{[0-9]{1,3}(?:,[0-9]{0,3})?\}|[?+*]|\((?:\?:)?|\)|\|)~';

    /**
     * Checks a tool definition.
     *
     * @param McpToolDefinition $definition the definition
     *
     * @throws LogicException on the first violation
     */
    public static function checkTool(McpToolDefinition $definition): void
    {
        self::checkName($definition->name);
        self::checkLabel($definition->title, 128, false, 'title');
        self::checkLabel($definition->description, 2000, true, 'description');

        $input = $definition->inputSchema;
        if (($input['type'] ?? null) !== 'object' || ($input['additionalProperties'] ?? null) !== false) {
            throw new LogicException('inputSchema: the root must be "type": "object" with "additionalProperties": false.');
        }
        self::walk($input, 'inputSchema#', true, false, 0);
        self::checkOutputRoot($definition->outputSchema);
        self::walk($definition->outputSchema, 'outputSchema#', false, false, 0);
    }

    /**
     * Checks a resource template definition.
     *
     * @param McpResourceTemplateDefinition $definition the definition
     *
     * @throws LogicException on the first violation
     */
    public static function checkTemplate(McpResourceTemplateDefinition $definition): void
    {
        self::checkName($definition->name);
        self::checkLabel($definition->title, 128, false, 'title');
        self::checkLabel($definition->description, 2000, true, 'description');

        $template = $definition->uriTemplate;
        if (strlen($template) > 512 || preg_match('/^[a-z][a-z0-9+.-]*:\/\/[^\s{}]*(?:\{[a-z_][a-z0-9_]*\}[^\s{}]*)*$/', $template) !== 1) {
            throw new LogicException('uriTemplate must be "scheme://…" with {variable} placeholders (RFC 6570 level 1).');
        }
        preg_match_all('/\{([a-z_][a-z0-9_]*)\}/', $template, $matches);
        $placeholders = $matches[1];
        $variables    = array_keys($definition->variables);
        if (count(array_unique($placeholders)) !== count($placeholders) || array_diff($placeholders, $variables) !== [] || array_diff($variables, $placeholders) !== []) {
            throw new LogicException('Each placeholder must appear once and have exactly one entry in variables.');
        }
        foreach ($definition->variables as $name => $pattern) {
            if ($pattern === '' || strlen($pattern) > 256 || @preg_match('#^' . $pattern . '$#Du', '') === false) {
                throw new LogicException(sprintf('variables.%s must be a valid regular expression of at most 256 characters.', $name));
            }
        }
        if (@preg_match($definition->pattern(), '') === false) {
            throw new LogicException('The URI template does not compile to a valid pattern.');
        }
        self::checkOutputRoot($definition->outputSchema);
        self::walk($definition->outputSchema, 'outputSchema#', false, false, 0);
    }

    /**
     * Checks a tool or template name.
     *
     * @param string $name the name
     *
     * @throws LogicException when invalid
     */
    public static function checkName(string $name): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new LogicException(sprintf('Name "%s" must match %s.', $name, self::NAME_PATTERN));
        }
    }

    /**
     * Checks a static label: length and no control characters (new lines allowed in descriptions).
     *
     * @param string $value the value
     * @param int $max maximum length
     * @param bool $multiline whether new lines are allowed
     * @param string $field the field name, for the message
     *
     * @throws LogicException when invalid
     */
    public static function checkLabel(string $value, int $max, bool $multiline, string $field): void
    {
        $control = $multiline ? '/[\x{0}-\x{9}\x{B}-\x{1F}\x{7F}-\x{9F}\p{Cf}]/u' : '/[\p{Cc}\p{Cf}]/u';
        if ($value === '' || mb_strlen($value) > $max || preg_match($control, $value) !== 0) {
            throw new LogicException(sprintf('%s must be 1 to %d characters without control or format characters.', $field, $max));
        }
    }

    /**
     * Walks one schema.
     *
     * @param mixed $schema the schema
     * @param string $path JSON pointer-like path, for messages
     * @param bool $input input (true) or output (false) rules
     * @param bool $untrusted whether the schema sits under an output `untrusted` object
     * @param int $depth nesting depth
     *
     * @throws LogicException on the first violation
     */
    private static function walk(mixed $schema, string $path, bool $input, bool $untrusted, int $depth): void
    {
        if (!is_array($schema) || ($schema !== [] && array_is_list($schema))) {
            throw new LogicException($path . ': a schema must be an object.');
        }
        if ($depth > self::MAX_DEPTH) {
            throw new LogicException($path . ': nested deeper than ' . self::MAX_DEPTH . '.');
        }
        $unknown = array_diff(array_keys($schema), self::KEYWORDS);
        if ($unknown !== []) {
            throw new LogicException($path . ': keyword(s) not allowed: ' . implode(', ', $unknown) . '.');
        }
        if (isset($schema['$ref']) && (!is_string($schema['$ref']) || !str_starts_with($schema['$ref'], '#/$defs/'))) {
            throw new LogicException($path . ': $ref must point to "#/$defs/…".');
        }
        self::checkValues($schema, $path);
        $types  = self::types($schema, $path);
        $strict = $input || !$untrusted;
        if ($strict && $types === [] && array_intersect(array_keys($schema), ['const', 'enum', '$ref', 'oneOf']) === []) {
            throw new LogicException($path . ': declare "type", "const", "enum", "$ref" or "oneOf".');
        }
        if ($strict) {
            self::checkTypedRules($schema, $types, $path, $input);
        }

        foreach (is_array($schema['properties'] ?? null) ? $schema['properties'] : [] as $name => $property) {
            $isUntrusted = !$input && !$untrusted && $name === 'untrusted';
            if ($isUntrusted && (!is_array($property) || ($property['type'] ?? null) !== 'object')) {
                throw new LogicException($path . '/properties/untrusted: must be "type": "object".');
            }
            self::walk($property, $path . '/properties/' . $name, $input, $untrusted || $isUntrusted, $depth + 1);
        }
        if (isset($schema['items'])) {
            self::walk($schema['items'], $path . '/items', $input, $untrusted, $depth + 1);
        }
        if (isset($schema['oneOf'])) {
            if (!is_array($schema['oneOf']) || !array_is_list($schema['oneOf']) || count($schema['oneOf']) > self::MAX_BRANCHES) {
                throw new LogicException($path . '/oneOf: a list of at most ' . self::MAX_BRANCHES . ' schemas.');
            }
            foreach ($schema['oneOf'] as $index => $branch) {
                self::walk($branch, $path . '/oneOf/' . $index, $input, $untrusted, $depth + 1);
            }
        }
        foreach (is_array($schema['$defs'] ?? null) ? $schema['$defs'] : [] as $name => $definition) {
            self::walk($definition, $path . '/$defs/' . $name, $input, false, $depth + 1);
        }
    }

    /**
     * The declared types.
     *
     * @param array<string, mixed> $schema the schema
     * @param string $path the path
     *
     * @throws LogicException on an unknown type
     *
     * @return list<string> the types
     */
    private static function types(array $schema, string $path): array
    {
        $type  = $schema['type'] ?? [];
        $types = is_string($type) ? [$type] : $type;
        if (!is_array($types) || array_diff($types, self::TYPES) !== []) {
            throw new LogicException($path . ': unknown type.');
        }

        $list = [];
        foreach ($types as $item) {
            $list[] = (string) $item;
        }

        return $list;
    }

    /**
     * Rules for strings, arrays and objects outside `untrusted`.
     *
     * @param array<string, mixed> $schema the schema
     * @param list<string> $types the declared types
     * @param string $path the path
     * @param bool $input input rules
     *
     * @throws LogicException on the first violation
     */
    private static function checkTypedRules(array $schema, array $types, string $path, bool $input): void
    {
        $bounded = isset($schema['enum']) || isset($schema['const']);
        if (in_array('string', $types, true) && !$bounded) {
            if ($input && !is_int($schema['maxLength'] ?? null)) {
                throw new LogicException($path . ': a string argument needs "maxLength".');
            }
            if (!$input) {
                self::checkTrustedString($schema, $path);
            }
        }
        if (in_array('array', $types, true)) {
            if ($input && !is_int($schema['maxItems'] ?? null)) {
                throw new LogicException($path . ': an array argument needs "maxItems".');
            }
            if (!isset($schema['items'])) {
                throw new LogicException($path . ': an array needs "items".');
            }
        }
        if (in_array('object', $types, true) && ($schema['additionalProperties'] ?? null) !== false) {
            throw new LogicException($path . ': an object needs "additionalProperties": false.');
        }
    }

    /**
     * A string outside `untrusted` must not be able to carry free text (audit IH-L3): `format` uuid, date or date-time; or
     * `format: uri` limited to http(s) with `maxLength` <= 2,048 and a pattern `^https://…$` or `^https?://…$` whose rest
     * is made of safe tokens too; or an anchored pattern of safe tokens (no wildcard, no whitespace, no top-level
     * alternation) with `maxLength` <= 512.
     *
     * @param array<string, mixed> $schema the schema
     * @param string $path the path
     *
     * @throws LogicException when the string could carry free text
     */
    private static function checkTrustedString(array $schema, string $path): void
    {
        $format  = $schema['format'] ?? null;
        $pattern = $schema['pattern'] ?? null;
        $max     = $schema['maxLength'] ?? null;
        if (in_array($format, self::TRUSTED_FORMATS, true)) {
            return;
        }
        if ($format === 'uri') {
            if (!is_int($max) || $max > self::MAX_URI_LENGTH || !is_string($pattern) || preg_match('~^\\^https\\??://(.+)$~s', $pattern, $rest) !== 1 || !self::isSafePattern('^' . $rest[1])) {
                throw new LogicException($path . ': a trusted "format": "uri" needs "maxLength" <= ' . self::MAX_URI_LENGTH . ' and a "pattern" "^https://…$" or "^https?://…$" whose rest is a safe pattern (URLs written by people go under "untrusted").');
            }

            return;
        }
        if ($format !== null || !is_string($pattern) || !self::isSafePattern($pattern) || !is_int($max) || $max > self::MAX_PATTERNED_LENGTH) {
            throw new LogicException($path . ': free text must sit under "untrusted" (outside it, a string needs "format" uuid/date/date-time, "enum", "const", an http(s) "uri", or an anchored safe "pattern" with "maxLength" <= ' . self::MAX_PATTERNED_LENGTH . ').');
        }
    }

    /**
     * Whether a pattern is `^…$` made only of safe tokens.
     *
     * @param string $pattern the pattern
     *
     * @return bool true when safe
     */
    private static function isSafePattern(string $pattern): bool
    {
        if (strlen($pattern) < 3 || !str_starts_with($pattern, '^') || !str_ends_with($pattern, '$') || str_ends_with($pattern, '\\$')) {
            return false;
        }
        $body   = substr($pattern, 1, -1);
        $offset = 0;
        $depth  = 0;
        $length = strlen($body);
        while ($offset < $length) {
            if (preg_match(self::SAFE_PATTERN_TOKEN, $body, $match, 0, $offset) !== 1) {
                return false;
            }
            $depth += match ($match[0]) {
                '(', '(?:' => 1,
                ')'        => -1,
                default    => 0,
            };
            // `^a|b$` means `(^a)|(b$)`: an alternation outside a group defeats the anchors (audit IH-L3r).
            if ($depth < 0 || ($match[0] === '|' && $depth === 0)) {
                return false;
            }
            $offset += strlen($match[0]);
        }

        return $depth === 0;
    }

    /**
     * The output root must be an object (MCP: `structuredContent` of a tool with an output schema is an object).
     *
     * @param array<string, mixed> $schema the output schema
     *
     * @throws LogicException otherwise
     */
    private static function checkOutputRoot(array $schema): void
    {
        if (($schema['type'] ?? null) !== 'object') {
            throw new LogicException('outputSchema: the root must be "type": "object".');
        }
    }

    /**
     * Checks keyword values: numbers, non-negative integers, booleans, strings, lists and regular expressions.
     *
     * @param array<string, mixed> $schema the schema
     * @param string $path the path
     *
     * @throws LogicException on an invalid value
     */
    private static function checkValues(array $schema, string $path): void
    {
        foreach ($schema as $keyword => $value) {
            $valid = match ($keyword) {
                'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum' => is_int($value) || is_float($value),
                'minLength', 'maxLength', 'minItems', 'maxItems'             => is_int($value) && $value >= 0,
                'uniqueItems', 'additionalProperties'                        => is_bool($value) || ($keyword === 'additionalProperties' && is_array($value)),
                'format', 'description', 'title'                             => is_string($value),
                'pattern'                                                    => is_string($value) && @preg_match('/' . str_replace('/', '\\/', $value) . '/u', '') !== false,
                'required'                                                   => is_array($value) && array_is_list($value) && array_filter($value, is_string(...)) === $value,
                'enum'                                                       => is_array($value) && array_is_list($value) && $value !== [],
                'properties', '$defs'                                        => is_array($value),
                default                                                      => true,
            };
            if (!$valid) {
                throw new LogicException(sprintf('%s: invalid value for "%s".', $path, $keyword));
            }
        }
    }
}

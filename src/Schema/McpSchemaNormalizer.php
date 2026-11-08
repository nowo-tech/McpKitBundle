<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Schema;

use stdClass;

use function array_is_list;
use function array_map;
use function in_array;
use function is_array;

/**
 * Turns a schema written as PHP arrays into JSON objects: maps (`properties`, `$defs`) and schemas become `stdClass`, so
 * an empty `properties` encodes as `{}`, never `[]`.
 */
final class McpSchemaNormalizer
{
    /** Keywords whose value is a schema. */
    private const SCHEMA_KEYWORDS = ['items', 'additionalProperties', 'not', 'contains', 'propertyNames', 'if', 'then', 'else'];

    /** Keywords whose value is a list of schemas. */
    private const SCHEMA_LIST_KEYWORDS = ['oneOf', 'anyOf', 'allOf', 'prefixItems'];

    /** Keywords whose value is a map of schemas. */
    private const SCHEMA_MAP_KEYWORDS = ['properties', '$defs', 'patternProperties'];

    /**
     * Normalizes a schema.
     *
     * @param array<string, mixed> $schema the schema
     *
     * @return stdClass the JSON schema object
     */
    public static function toObject(array $schema): stdClass
    {
        $object = new stdClass();
        foreach ($schema as $keyword => $value) {
            $object->{$keyword} = self::value((string) $keyword, $value);
        }

        return $object;
    }

    /**
     * Normalizes one keyword value.
     *
     * @param string $keyword the keyword
     * @param mixed $value the value
     *
     * @return mixed the JSON value
     */
    private static function value(string $keyword, mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (in_array($keyword, self::SCHEMA_KEYWORDS, true)) {
            return self::toObject($value);
        }
        if (in_array($keyword, self::SCHEMA_LIST_KEYWORDS, true)) {
            return array_map(static fn (mixed $item): mixed => is_array($item) ? self::toObject($item) : $item, $value);
        }
        if (in_array($keyword, self::SCHEMA_MAP_KEYWORDS, true)) {
            $map = new stdClass();
            foreach ($value as $name => $item) {
                $map->{$name} = is_array($item) ? self::toObject($item) : $item;
            }

            return $map;
        }

        return array_is_list($value) ? $value : self::toObject($value);
    }
}

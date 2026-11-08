<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Schema;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Parsers\SchemaParser;
use Opis\JsonSchema\SchemaLoader;
use Opis\JsonSchema\Validator;
use stdClass;

use function implode;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;

/**
 * Validates JSON values against JSON Schema 2020-12 (opis/json-schema) with no remote loader: only local `$defs`
 * resolve. A new validator per call, so nothing is kept between requests.
 */
final class McpSchemaValidator
{
    /**
     * Validates a value.
     *
     * @param mixed $data the JSON value (objects as `stdClass`)
     * @param stdClass $schema the schema object
     *
     * @return string|null null when valid, else a static path such as `due.date` (schema names and indexes only)
     */
    public static function violation(mixed $data, stdClass $schema): ?string
    {
        $error = (new Validator(new SchemaLoader(new SchemaParser(), null, false)))->validate($data, $schema)->error();
        if (!$error instanceof ValidationError) {
            return null;
        }
        while ($error->subErrors() !== []) {
            $error = $error->subErrors()[0];
        }
        $segments = [];
        foreach ($error->data()->fullPath() as $segment) {
            $segments[] = self::segment($segment);
        }
        $missing = $error->args()['missing'] ?? null;
        if ($error->keyword() === 'required' && is_array($missing) && isset($missing[0])) {
            $segments[] = self::segment($missing[0]);
        }

        return implode('.', $segments);
    }

    /**
     * A path segment safe to show: an index, a plain name, or `?`.
     *
     * @param mixed $segment the raw segment
     *
     * @return string the segment
     */
    private static function segment(mixed $segment): string
    {
        if (is_int($segment)) {
            return (string) $segment;
        }

        return is_string($segment) && preg_match('/^[A-Za-z0-9_]{1,64}$/', $segment) === 1 ? $segment : '?';
    }
}

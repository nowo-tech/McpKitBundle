<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Http;

use WeakMap;

/**
 * Holds the raw values of the sensitive headers of each {@see McpHttpRequest} outside the object, so no dump of the
 * object (VarDumper, `var_export()`, `(array)` cast, `print_r()`) can reach them.
 *
 * Keyed by the request object in a {@see WeakMap}: an entry disappears with its request, so nothing outlives the
 * request in a FrankenPHP worker (no reset needed).
 *
 * @internal
 */
final class McpHttpRequestSecrets
{
    /** @var WeakMap<McpHttpRequest, array<string, list<string>>>|null */
    private static ?WeakMap $values = null; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty (WeakMap keyed by the per-request VO)

    /**
     * Stores the sensitive header values of a request.
     *
     * @param McpHttpRequest $request the request
     * @param array<string, list<string>> $headers lower-case header name => raw values
     */
    public static function put(McpHttpRequest $request, array $headers): void
    {
        // A WeakMap keyed by the per-request VO: each entry dies with its request, nothing crosses requests.
        // @igor-ignore - WeakMap keyed by the per-request VO; entries are collected with it.
        self::$values ??= new WeakMap(); // @phpstan-ignore frankenphp.worker.noMutableStaticProperty
        // @igor-ignore - WeakMap keyed by the per-request VO; entries are collected with it.
        self::$values[$request] = $headers; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty
    }

    /**
     * The raw values of one sensitive header.
     *
     * @param McpHttpRequest $request the request
     * @param string $name lower-case header name
     *
     * @return list<string> the values, empty when unknown (e.g. after unserialize)
     */
    public static function get(McpHttpRequest $request, string $name): array
    {
        return self::$values[$request][$name] ?? [];
    }
}

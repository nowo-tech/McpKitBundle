<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Http;

use function array_slice;
use function explode;
use function in_array;
use function is_numeric;
use function strtolower;
use function trim;

/**
 * `Content-Type` and `Accept` rules of the endpoint: requests are `application/json` (UTF-8), answers are JSON only.
 */
final class McpMediaType
{
    /**
     * Whether a request `Content-Type` is JSON, with no charset or `utf-8`.
     *
     * @param string|null $contentType the header value
     *
     * @return bool true when acceptable
     */
    public static function isJsonContentType(?string $contentType): bool
    {
        if ($contentType === null) {
            return false;
        }
        $parts = explode(';', $contentType);
        if (strtolower(trim($parts[0])) !== 'application/json') {
            return false;
        }
        foreach (array_slice($parts, 1) as $parameter) {
            $pair = explode('=', $parameter, 2);
            if (strtolower(trim($pair[0])) === 'charset' && strtolower(trim(trim($pair[1] ?? ''), '"')) !== 'utf-8') {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether an `Accept` header lets the server answer `application/json`. A missing or empty header accepts anything.
     *
     * @param string|null $accept the header value
     *
     * @return bool true when JSON is acceptable
     */
    public static function acceptsJson(?string $accept): bool
    {
        if ($accept === null || trim($accept) === '') {
            return true;
        }
        foreach (explode(',', $accept) as $range) {
            $parts = explode(';', $range);
            $type  = strtolower(trim($parts[0]));
            if (!in_array($type, ['application/json', 'application/*', '*/*'], true)) {
                continue;
            }
            if (self::quality($parts) > 0.0) {
                return true;
            }
        }

        return false;
    }

    /**
     * The `q` parameter of a media range: 1 when absent, 0 when malformed.
     *
     * @param list<string> $parts the range split on `;`
     *
     * @return float the quality
     */
    private static function quality(array $parts): float
    {
        foreach (array_slice($parts, 1) as $parameter) {
            $pair = explode('=', $parameter, 2);
            if (strtolower(trim($pair[0])) === 'q') {
                return isset($pair[1]) && is_numeric(trim($pair[1])) ? (float) trim($pair[1]) : 0.0;
            }
        }

        return 1.0;
    }
}

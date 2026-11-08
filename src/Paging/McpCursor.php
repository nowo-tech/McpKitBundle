<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Paging;

use JsonException;
use Nowo\McpKitBundle\Tool\McpToolError;

use function array_is_list;
use function base64_decode;
use function base64_encode;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function rtrim;
use function str_pad;
use function strlen;
use function strtr;

use const JSON_THROW_ON_ERROR;
use const STR_PAD_RIGHT;

/**
 * Opaque list cursors: base64url JSON of a flat map of scalars, strictly validated. A cursor is a position, not a
 * credential: access is checked again on every call.
 */
final class McpCursor
{
    public const MAX_LENGTH = 512;

    /**
     * Encodes a position.
     *
     * @param array<string, bool|int|string|null> $state the position (e.g. `['o' => 50]`)
     *
     * @return string the cursor
     */
    public static function encode(array $state): string
    {
        return rtrim(strtr(base64_encode(json_encode($state, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /**
     * Decodes a cursor.
     *
     * @param string|null $cursor the cursor argument
     *
     * @throws McpToolError `INPUT_002` "Invalid field: cursor" when malformed
     *
     * @return array<string, bool|int|string|null>|null the position, or null when no cursor was given
     */
    public static function decode(?string $cursor): ?array
    {
        if ($cursor === null) {
            return null;
        }
        $invalid = new McpToolError('INPUT_002', 'Invalid field: cursor');
        if ($cursor === '' || strlen($cursor) > self::MAX_LENGTH || preg_match('/^[A-Za-z0-9_-]+$/', $cursor) !== 1) {
            throw $invalid;
        }
        $json = base64_decode(str_pad(strtr($cursor, '-_', '+/'), strlen($cursor) + (4 - strlen($cursor) % 4) % 4, '=', STR_PAD_RIGHT), true);
        try {
            $state = is_string($json) ? json_decode($json, true, 2, JSON_THROW_ON_ERROR) : null;
        } catch (JsonException) {
            throw $invalid;
        }
        if (!is_array($state) || $state === [] || array_is_list($state)) {
            throw $invalid;
        }
        foreach ($state as $value) {
            if ($value !== null && !is_int($value) && !is_string($value) && !is_bool($value)) {
                throw $invalid;
            }
        }

        /* @var array<string, int|string|bool|null> $state */
        return $state;
    }

    /**
     * The input-schema fragment of a `cursor` argument.
     *
     * @return array{type: string, maxLength: int, pattern: string, description: string}
     */
    public static function schema(): array
    {
        return [
            'type'        => 'string',
            'maxLength'   => self::MAX_LENGTH,
            'pattern'     => '^[A-Za-z0-9_-]+$',
            'description' => 'Opaque cursor from next_cursor of the previous page.',
        ];
    }
}

<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Envelope;

use LogicException;
use Nowo\McpKitBundle\Tool\McpUntrusted;
use stdClass;

use function array_is_list;
use function is_array;
use function is_object;
use function is_scalar;
use function json_encode;
use function mb_ord;
use function preg_replace_callback;
use function sprintf;

use const JSON_HEX_AMP;
use const JSON_HEX_TAG;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The untrusted envelope: turns a tool's data into the JSON value of `structuredContent` and the text block that mirrors
 * it. {@see McpUntrusted} groups become `untrusted` objects; the text block is JSON, so a value can never close a quote
 * or a fake marker, and characters that hide text from people (C1 controls, bidi controls, zero-width characters,
 * Unicode tag characters) are shown as `\uXXXX` escapes. The decoded JSON is identical.
 */
final class McpEnvelope
{
    /** Characters escaped in the text block. */
    private const HIDDEN = '/[\x{80}-\x{9F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2066}-\x{2069}\x{FEFF}\x{E0000}-\x{E007F}]/u';

    /**
     * Converts tool data to its JSON value.
     *
     * @param array<array-key, mixed> $data the tool's data
     *
     * @throws LogicException when `untrusted` is misused or a value is not JSON data
     *
     * @return array{json: mixed, untrusted: bool} the JSON value (objects as `stdClass`) and whether it holds untrusted values
     */
    public static function convert(array $data): array
    {
        $untrusted = false;
        $json      = self::value($data, false, $untrusted);

        return ['json' => $json, 'untrusted' => $untrusted];
    }

    /**
     * The text form of a JSON value.
     *
     * @param mixed $json the JSON value
     *
     * @return string the text block
     */
    public static function text(mixed $json): string
    {
        $encoded = json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        return (string) preg_replace_callback(self::HIDDEN, static fn (array $match): string => self::escape($match[0]), $encoded);
    }

    /**
     * Converts one value.
     *
     * @param mixed $value the value
     * @param bool $inUntrusted whether the value sits inside an untrusted group
     * @param bool $untrusted set when an untrusted group is met
     *
     * @throws LogicException on misuse
     *
     * @return mixed the JSON value
     */
    private static function value(mixed $value, bool $inUntrusted, bool &$untrusted): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }
        if ($value instanceof McpUntrusted) {
            throw new LogicException('McpUntrusted must be the value of an "untrusted" key.');
        }
        if ($value instanceof stdClass) {
            $value  = (array) $value;
            $object = true;
        } elseif (is_array($value)) {
            $object = $value !== [] && !array_is_list($value);
        } else {
            throw new LogicException('Tool data must be arrays, stdClass and scalars (got ' . (is_object($value) ? $value::class : 'a resource') . ').');
        }

        $converted = [];
        foreach ($value as $key => $item) {
            if ($key === 'untrusted' && $object && !$inUntrusted) {
                if (!$item instanceof McpUntrusted) {
                    throw new LogicException('The "untrusted" key must hold McpUntrusted::of([...]).');
                }
                $untrusted       = true;
                $converted[$key] = (object) self::value($item->fields, true, $untrusted);

                continue;
            }
            $converted[$key] = self::value($item, $inUntrusted, $untrusted);
        }

        return $object ? (object) $converted : $converted;
    }

    /**
     * `\uXXXX` escape of one character (a surrogate pair above U+FFFF).
     *
     * @param string $character the character
     *
     * @return string the escape
     */
    private static function escape(string $character): string
    {
        $code = mb_ord($character, 'UTF-8');
        if ($code <= 0xFFFF) {
            return sprintf('\\u%04x', $code);
        }
        $code -= 0x10000;

        return sprintf('\\u%04x\\u%04x', 0xD800 | ($code >> 10), 0xDC00 | ($code & 0x3FF));
    }
}

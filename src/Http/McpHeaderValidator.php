<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Http;

use Nowo\McpKitBundle\Protocol\JsonRpcError;
use Nowo\McpKitBundle\Protocol\McpEra;
use Nowo\McpKitBundle\Protocol\McpProtocol;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

use function base64_decode;
use function count;
use function is_string;
use function mb_check_encoding;
use function preg_match;

/**
 * Checks the mirrored request headers against the body (MCP 2026-07-28 Streamable HTTP, "Server Validation").
 *
 * Modern requests must carry `Mcp-Method`, and `Mcp-Name` for `tools/call`, `resources/read` and `prompts/get`.
 * Legacy requests are checked only for the headers they send. Messages never echo a value.
 */
final class McpHeaderValidator
{
    /** Methods whose `Mcp-Name` mirrors `params.name` or `params.uri`. */
    public const NAMED_METHODS = ['tools/call' => 'name', 'resources/read' => 'uri', 'prompts/get' => 'name'];

    /**
     * Validates `Mcp-Method` and `Mcp-Name`.
     *
     * @param Request $request the request
     * @param McpEra $era era of the request
     * @param string $method the body's method
     * @param stdClass $params the body's params
     *
     * @throws JsonRpcError 400 `-32020` on a missing, malformed or mismatched header
     */
    public static function validate(Request $request, McpEra $era, string $method, stdClass $params): void
    {
        $required     = $era === McpEra::Modern;
        $methodHeader = self::single($request, McpProtocol::HEADER_METHOD, $required);
        if ($methodHeader !== null && (!self::isPlain($methodHeader) || $methodHeader !== $method)) {
            throw JsonRpcError::headerMismatch(McpProtocol::HEADER_METHOD);
        }

        $field = self::NAMED_METHODS[$method] ?? null;
        if ($field === null) {
            return;
        }
        $nameHeader = self::single($request, McpProtocol::HEADER_NAME, $required);
        if ($nameHeader === null) {
            return;
        }
        $bodyValue = $params->{$field} ?? null;
        if (!is_string($bodyValue) || self::decode($nameHeader) !== $bodyValue) {
            throw JsonRpcError::headerMismatch(McpProtocol::HEADER_NAME);
        }
    }

    /**
     * Validates the `MCP-Protocol-Version` header of a modern request against the body's version.
     *
     * @param Request $request the request
     * @param string $bodyVersion the body's `_meta` protocol version
     *
     * @throws JsonRpcError 400 `-32020` when missing, repeated or different
     */
    public static function validateProtocolVersion(Request $request, string $bodyVersion): void
    {
        $header = self::single($request, McpProtocol::HEADER_PROTOCOL_VERSION, true);
        if ($header !== $bodyVersion) {
            throw JsonRpcError::headerMismatch(McpProtocol::HEADER_PROTOCOL_VERSION);
        }
    }

    /**
     * Decodes a header value that may use the Base64 sentinel `=?base64?…?=`.
     *
     * @param string $value the raw header value
     *
     * @return string|null the decoded value, or null when malformed
     */
    public static function decode(string $value): ?string
    {
        if (preg_match('/^=\?base64\?(.*)\?=$/s', $value, $match) === 1) {
            if (preg_match('#^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$#', $match[1]) !== 1) {
                return null;
            }
            $decoded = base64_decode($match[1], true);

            return is_string($decoded) && mb_check_encoding($decoded, 'UTF-8') ? $decoded : null;
        }

        return self::isPlain($value) ? $value : null;
    }

    /**
     * Whether a value is a plain header value: visible ASCII and inner spaces, no leading or trailing whitespace.
     *
     * @param string $value the value
     *
     * @return bool true when plain
     */
    public static function isPlain(string $value): bool
    {
        return preg_match('/^[\x21-\x7E](?:[\x20-\x7E]*[\x21-\x7E])?$/', $value) === 1;
    }

    /**
     * Reads a header that may appear once.
     *
     * @param Request $request the request
     * @param string $name the header name
     * @param bool $required whether a missing header is an error
     *
     * @throws JsonRpcError when missing (required) or repeated
     *
     * @return string|null the value, or null when absent and not required
     */
    private static function single(Request $request, string $name, bool $required): ?string
    {
        $values = $request->headers->all($name);
        if ($values === []) {
            if ($required) {
                throw JsonRpcError::headerMismatch($name);
            }

            return null;
        }
        if (count($values) !== 1 || !is_string($values[0]) || $values[0] === '') {
            throw JsonRpcError::headerMismatch($name);
        }

        return $values[0];
    }
}

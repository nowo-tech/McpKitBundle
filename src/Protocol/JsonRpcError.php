<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Protocol;

use RuntimeException;

/**
 * A JSON-RPC error answer with its HTTP status. The message is static text: it never holds a value from the request.
 *
 * `$legacyStatus` is the status on the legacy path, where JSON-RPC errors of method handlers are answered 200.
 */
final class JsonRpcError extends RuntimeException
{
    /**
     * Creates the error.
     *
     * @param int $errorCode JSON-RPC error code
     * @param string $errorMessage static message
     * @param int $status HTTP status on the modern path (and before the era is known)
     * @param array<string, mixed>|null $data `error.data`
     * @param array<string, string> $headers extra HTTP headers
     * @param int|null $legacyStatus HTTP status on the legacy path; null: same as `$status`
     */
    public function __construct(
        public readonly int $errorCode,
        public readonly string $errorMessage,
        public readonly int $status,
        public readonly ?array $data = null,
        public readonly array $headers = [],
        public readonly ?int $legacyStatus = null,
    ) {
        parent::__construct($errorMessage, $errorCode);
    }

    /**
     * The HTTP status for an era.
     *
     * @param McpEra|null $era era of the request, or null before it is known
     *
     * @return int HTTP status
     */
    public function statusFor(?McpEra $era): int
    {
        return $era === McpEra::Legacy && $this->legacyStatus !== null ? $this->legacyStatus : $this->status;
    }

    /**
     * Malformed request (400 `-32600`).
     *
     * @return self the error
     */
    public static function invalidRequest(): self
    {
        return new self(McpProtocol::INVALID_REQUEST, 'Invalid request', 400);
    }

    /**
     * Unknown or unsupported method (modern 404, legacy 200, `-32601`).
     *
     * @return self the error
     */
    public static function methodNotFound(): self
    {
        return new self(McpProtocol::METHOD_NOT_FOUND, 'Method not found', 404, null, [], 200);
    }

    /**
     * Params that do not match the method's request shape (modern 400, legacy 200, `-32602`).
     *
     * @param string $message static message
     *
     * @return self the error
     */
    public static function invalidParams(string $message = 'Invalid params'): self
    {
        return new self(McpProtocol::INVALID_PARAMS, $message, 400, null, [], 200);
    }

    /**
     * A semantic `-32602` of a method handler (unknown tool, unknown resource, bad cursor): 200 on both eras.
     *
     * @param string $message static message
     *
     * @return self the error
     */
    public static function invalidParamsOk(string $message): self
    {
        return new self(McpProtocol::INVALID_PARAMS, $message, 200);
    }

    /**
     * Header and body disagree, or a required header is missing or malformed (400 `-32020`).
     *
     * @param string $header header name (static)
     *
     * @return self the error
     */
    public static function headerMismatch(string $header): self
    {
        return new self(McpProtocol::HEADER_MISMATCH, 'Header mismatch: ' . $header, 400);
    }

    /**
     * Server error without detail (500 `-32603`).
     *
     * @return self the error
     */
    public static function internal(): self
    {
        return new self(McpProtocol::INTERNAL_ERROR, 'Internal error', 500);
    }
}

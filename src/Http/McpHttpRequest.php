<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Http;

use JsonSerializable;
use Nowo\McpKitBundle\Security\McpCredential;
use Nowo\McpKitBundle\Security\McpRequestAuthenticatorInterface;
use Nowo\McpKitBundle\Security\McpRequestAvailabilityInterface;

use function array_key_exists;
use function array_keys;
use function in_array;
use function is_string;
use function strtolower;
use function strtr;

/**
 * Framework-free view of the HTTP request that reached the MCP endpoint, for the host's authenticator and availability
 * hooks ({@see McpRequestAuthenticatorInterface},
 * {@see McpRequestAvailabilityInterface}) and for tools ({@see
 * \Nowo\McpKitBundle\Tool\McpToolCall::$http}).
 *
 * It carries the method, the path, the scheme and host, the client IP (after the host's trusted-proxy rules) and the
 * headers. It never carries the body: the hooks run before the body is read and capped, and tools receive the
 * validated arguments instead.
 *
 * Redaction is an exact allowlist: only the names in {@see SAFE_HEADERS} are kept in clear. The raw value of every other header (`Authorization`, `Cookie`, API keys, custom tokens, …) is never stored in
 * a property of this object: it lives in an internal {@see \WeakMap} keyed by the object and is readable through
 * {@see header()}, {@see headerValues()} and {@see credential()} only. Every other view of the object (VarDumper /
 * `dump()` / the profiler, `var_export()`, `print_r()`, `(array)` cast, {@see __toString()}, {@see jsonSerialize()},
 * `serialize()`) shows `[redacted]`. A copy restored by `unserialize()` or made by `clone` has no secret values
 * (`credential()` returns null).
 */
final readonly class McpHttpRequest implements JsonSerializable
{
    /** Replacement of a sensitive header value in every view of the object. */
    public const REDACTED = '[redacted]';

    /**
     * The only header names (lower case) shown in clear: exact names, no prefixes, so a custom `Mcp-Token` or an
     * `X-Forwarded-Access-Token` set by an auth proxy is redacted.
     */
    public const SAFE_HEADERS = [
        'accept', 'accept-encoding', 'accept-language', 'content-type', 'content-length', 'origin', 'user-agent', 'host',
        'x-forwarded-for', 'x-forwarded-proto', 'x-forwarded-host', 'x-forwarded-port', 'x-forwarded-prefix',
        'mcp-protocol-version', 'mcp-method', 'mcp-name',
    ];

    /** @var array<string, list<string>> lower-case header name => values, sensitive ones replaced by {@see REDACTED} */
    private array $headers;

    /**
     * Creates the request view.
     *
     * @param string $method upper-case HTTP method
     * @param string $path path info (without the query string)
     * @param array<string, list<string>|string> $headers header name => value or values (names are matched
     *                                                    case-insensitively, `_` and `-` alike)
     * @param string|null $clientIp client IP after the host's trusted-proxy rules, or null when unknown
     * @param string $scheme `http` or `https`
     * @param string $host host name, without the port
     */
    public function __construct(
        public string $method,
        public string $path,
        array $headers = [],
        public ?string $clientIp = null,
        public string $scheme = 'https',
        public string $host = '',
    ) {
        $normalized = [];
        $secrets    = [];
        foreach ($headers as $name => $values) {
            $key       = self::normalizeName((string) $name);
            $sensitive = self::isSensitiveHeader($key);
            foreach (is_string($values) ? [$values] : $values as $value) {
                $normalized[$key][] = $sensitive ? self::REDACTED : $value;
                if ($sensitive) {
                    $secrets[$key][] = $value;
                }
            }
        }
        $this->headers = $normalized;
        if ($secrets !== []) {
            McpHttpRequestSecrets::put($this, $secrets);
        }
    }

    /**
     * The first value of a header.
     *
     * @param string $name header name (case-insensitive)
     *
     * @return string|null the value, or null when absent
     */
    public function header(string $name): ?string
    {
        return $this->headerValues($name)[0] ?? null;
    }

    /**
     * Every value of a header.
     *
     * @param string $name header name (case-insensitive)
     *
     * @return list<string> the values, empty when absent
     */
    public function headerValues(string $name): array
    {
        $key = self::normalizeName($name);

        return self::isSensitiveHeader($key) ? McpHttpRequestSecrets::get($this, $key) : ($this->headers[$key] ?? []);
    }

    /**
     * Whether a header is present.
     *
     * @param string $name header name (case-insensitive)
     *
     * @return bool true when present
     */
    public function hasHeader(string $name): bool
    {
        return array_key_exists(self::normalizeName($name), $this->headers);
    }

    /**
     * Names of the headers present (lower case).
     *
     * @return list<string> the names
     */
    public function headerNames(): array
    {
        return array_keys($this->headers);
    }

    /**
     * The credential: the `Authorization` header only, with or without `Bearer`; never the query string, where it
     * would reach logs. Same rules as {@see McpCredential::fromHeader()}.
     *
     * @return string|null the credential, or null when there is none
     */
    public function credential(): ?string
    {
        return McpCredential::fromHeader($this->header('Authorization'));
    }

    /**
     * Whether a header name is treated as a secret: every header outside the exact allowlist {@see SAFE_HEADERS}.
     *
     * @param string $name header name (case-insensitive)
     *
     * @return bool true for credential-like headers
     */
    public static function isSensitiveHeader(string $name): bool
    {
        $key = self::normalizeName($name);

        return !in_array($key, self::SAFE_HEADERS, true);
    }

    /**
     * The headers with every sensitive value replaced by {@see REDACTED}.
     *
     * @return array<string, list<string>> lower-case header name => values
     */
    public function redactedHeaders(): array
    {
        $redacted = [];
        foreach ($this->headers as $name => $values) {
            $redacted[$name] = self::isSensitiveHeader($name) ? [self::REDACTED] : $values;
        }

        return $redacted;
    }

    /**
     * A short, secret-free label: method and path.
     *
     * @return string e.g. `POST /mcp`
     */
    public function __toString(): string
    {
        return $this->method . ' ' . $this->path;
    }

    /**
     * Dump form (`var_dump()`, `print_r()`): credentials redacted. VarDumper, `var_export()` and `(array)` read the
     * properties, which never hold a raw sensitive value.
     *
     * @return array<string, mixed> the redacted fields
     */
    public function __debugInfo(): array
    {
        return $this->redactedFields();
    }

    /**
     * JSON form (Monolog context, API output): credentials redacted.
     *
     * @return array<string, mixed> the redacted fields
     */
    public function jsonSerialize(): array
    {
        return $this->redactedFields();
    }

    /**
     * Serialized form: credentials redacted, so a cached or queued copy never holds them.
     *
     * @return array<string, mixed> the redacted fields
     */
    public function __serialize(): array
    {
        return $this->redactedFields();
    }

    /**
     * Restores a serialized (redacted) copy.
     *
     * @param array<string, mixed> $data the serialized fields
     */
    public function __unserialize(array $data): void
    {
        $this->method   = is_string($data['method'] ?? null) ? $data['method'] : '';
        $this->path     = is_string($data['path'] ?? null) ? $data['path'] : '';
        $this->scheme   = is_string($data['scheme'] ?? null) ? $data['scheme'] : 'https';
        $this->host     = is_string($data['host'] ?? null) ? $data['host'] : '';
        $this->clientIp = is_string($data['clientIp'] ?? null) ? $data['clientIp'] : null;
        $headers        = [];
        foreach ((array) ($data['headers'] ?? []) as $name => $values) {
            $key = self::normalizeName((string) $name);
            foreach ((array) $values as $value) {
                if (is_string($value)) {
                    // No secret store entry: a restored copy never yields a credential.
                    $headers[$key][] = self::isSensitiveHeader($key) ? self::REDACTED : $value;
                }
            }
        }
        $this->headers = $headers;
    }

    /**
     * The fields of every loggable form.
     *
     * @return array{method: string, path: string, scheme: string, host: string, clientIp: string|null, headers: array<string, list<string>>}
     */
    private function redactedFields(): array
    {
        return [
            'method'   => $this->method,
            'path'     => $this->path,
            'scheme'   => $this->scheme,
            'host'     => $this->host,
            'clientIp' => $this->clientIp,
            'headers'  => $this->redactedHeaders(),
        ];
    }

    /**
     * Normalizes a header name like Symfony's HeaderBag: lower case, `_` read as `-`.
     *
     * @param string $name the name
     *
     * @return string the normalized name
     */
    private static function normalizeName(string $name): string
    {
        return strtr(strtolower($name), '_', '-');
    }
}

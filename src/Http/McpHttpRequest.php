<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Http;

use JsonSerializable;
use Nowo\McpKitBundle\Security\McpCredential;
use Nowo\McpKitBundle\Security\McpRequestAuthenticatorInterface;
use Nowo\McpKitBundle\Security\McpRequestAvailabilityInterface;

use function array_key_exists;
use function array_keys;
use function array_values;
use function in_array;
use function is_string;
use function preg_match;
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
 * Headers that hold credentials (`Authorization`, `Cookie`, API keys, …) are readable through {@see header()} and
 * {@see credential()} only. Every loggable form of the object ({@see __toString()}, {@see __debugInfo()},
 * {@see jsonSerialize()}, `serialize()`) redacts them, so a VO that reaches a log, a dump or a cache never holds the
 * token.
 */
final readonly class McpHttpRequest implements JsonSerializable
{
    /** Replacement of a sensitive header value in every loggable form. */
    public const REDACTED = '[redacted]';

    /** Header names (lower case) always treated as secrets. */
    public const SENSITIVE_HEADERS = ['authorization', 'proxy-authorization', 'cookie', 'set-cookie', 'x-api-key', 'x-auth-token', 'x-csrf-token', 'x-xsrf-token', 'php-auth-pw', 'php-auth-user', 'php-auth-digest'];

    /** Any other header whose name matches this pattern is treated as a secret too. */
    private const SENSITIVE_PATTERN = '/(auth|token|secret|password|passwd|cookie|session|api[-_]?key|credential|signature)/i';

    /** @var array<string, list<string>> lower-case header name => values */
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
        foreach ($headers as $name => $values) {
            $key = self::normalizeName((string) $name);
            foreach (is_string($values) ? [$values] : $values as $value) {
                $normalized[$key][] = $value;
            }
        }
        $this->headers = $normalized;
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
        return $this->headers[self::normalizeName($name)][0] ?? null;
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
        return $this->headers[self::normalizeName($name)] ?? [];
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
     * Whether a header name is treated as a secret (redacted in every loggable form).
     *
     * @param string $name header name (case-insensitive)
     *
     * @return bool true for credential-like headers
     */
    public static function isSensitiveHeader(string $name): bool
    {
        $key = self::normalizeName($name);

        return in_array($key, self::SENSITIVE_HEADERS, true) || preg_match(self::SENSITIVE_PATTERN, $key) === 1;
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
            $redacted[$name] = self::isSensitiveHeader($name) ? [self::REDACTED] : array_values($values);
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
     * Dump form (`var_dump()`, Symfony VarDumper): credentials redacted.
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
            foreach ((array) $values as $value) {
                if (is_string($value)) {
                    $headers[self::normalizeName((string) $name)][] = $value;
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

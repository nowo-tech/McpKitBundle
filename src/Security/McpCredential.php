<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

use Nowo\McpKitBundle\Http\McpHttpRequest;
use Symfony\Component\HttpFoundation\Request;

use function is_string;
use function preg_replace;
use function trigger_error;
use function trim;

use const E_USER_DEPRECATED;

/**
 * The credential of a request: the `Authorization` header only, with or without `Bearer`; never the query string, where
 * it would reach logs.
 */
final class McpCredential
{
    /**
     * Reads the credential from the raw `Authorization` header value.
     *
     * @param string|null $authorization the `Authorization` header value, or null when absent
     *
     * @return string|null the credential, or null when there is none
     */
    public static function fromHeader(?string $authorization): ?string
    {
        if (!is_string($authorization)) {
            return null;
        }
        $credential = trim((string) preg_replace('/^Bearer(\s+|$)/i', '', trim($authorization)));

        return $credential === '' ? null : $credential;
    }

    /**
     * Reads the credential.
     *
     * @param Request $request the request
     *
     * @return string|null the credential, or null when there is none
     *
     * @deprecated since 1.1.0, use {@see McpHttpRequest::credential()} (or {@see fromHeader()});
     *             removed in 2.0.0
     */
    public static function fromRequest(Request $request): ?string
    {
        @trigger_error('Since nowo-tech/mcp-kit-bundle 1.1.0: McpCredential::fromRequest() is deprecated, use McpHttpRequest::credential() or McpCredential::fromHeader() instead.', E_USER_DEPRECATED);

        return self::fromHeader($request->headers->get('Authorization'));
    }
}

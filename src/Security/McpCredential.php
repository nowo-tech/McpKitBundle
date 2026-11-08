<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

use Symfony\Component\HttpFoundation\Request;

use function is_string;
use function preg_replace;
use function trim;

/**
 * The credential of a request: the `Authorization` header only, with or without `Bearer`; never the query string, where
 * it would reach logs.
 */
final class McpCredential
{
    /**
     * Reads the credential.
     *
     * @param Request $request the request
     *
     * @return string|null the credential, or null when there is none
     */
    public static function fromRequest(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');
        if (!is_string($header)) {
            return null;
        }
        $credential = trim((string) preg_replace('/^Bearer(\s+|$)/i', '', trim($header)));

        return $credential === '' ? null : $credential;
    }
}

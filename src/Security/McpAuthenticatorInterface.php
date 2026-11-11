<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

use Nowo\McpKitBundle\Http\McpHttpRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * The host's token check, required with `enabled: true` (the bundle stores no tokens). Called once per request; read the
 * credential with {@see McpCredential::fromHeader()}.
 *
 * @deprecated since 1.1.0, implement {@see McpRequestAuthenticatorInterface} instead (it receives the framework-free
 *             {@see McpHttpRequest}); removed in 2.0.0
 */
interface McpAuthenticatorInterface
{
    /**
     * Authenticates the request.
     *
     * @param Request $request the MCP request (its `Authorization` header holds the credential)
     *
     * @return McpAuthFailure|McpPrincipal the principal, or why there is none
     */
    public function authenticate(Request $request): McpPrincipal|McpAuthFailure;
}

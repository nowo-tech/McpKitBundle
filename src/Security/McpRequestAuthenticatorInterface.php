<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

use Nowo\McpKitBundle\Http\McpHttpRequest;

/**
 * The host's token check, required with `enabled: true` (the bundle stores no tokens). Called once per request with a
 * framework-free view of the request; read the credential with {@see McpHttpRequest::credential()}.
 *
 * Replaces {@see McpAuthenticatorInterface} (same contract, no HttpFoundation type). When a service implements both,
 * the bundle calls this one.
 */
interface McpRequestAuthenticatorInterface
{
    /**
     * Authenticates the request.
     *
     * @param McpHttpRequest $request the MCP request (its `Authorization` header holds the credential)
     *
     * @return McpAuthFailure|McpPrincipal the principal, or why there is none
     */
    public function authenticateRequest(McpHttpRequest $request): McpPrincipal|McpAuthFailure;
}

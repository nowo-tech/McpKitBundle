<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures;

use Nowo\McpKitBundle\Http\McpHttpRequest;
use Nowo\McpKitBundle\Security\McpAuthenticatorInterface;
use Nowo\McpKitBundle\Security\McpAuthFailure;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Security\McpRequestAuthenticatorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Implements both ports, as a host in the middle of the migration: the deprecated method refuses everything, so a test
 * sees which one the bundle calls.
 */
final class FixtureDualAuthenticator implements McpAuthenticatorInterface, McpRequestAuthenticatorInterface
{
    public function authenticate(Request $request): McpPrincipal|McpAuthFailure
    {
        // Any value the shared table does not know: refused.
        return FixtureMcpAuthenticator::answer('deprecated-port-called');
    }

    public function authenticateRequest(McpHttpRequest $request): McpPrincipal|McpAuthFailure
    {
        return FixtureMcpAuthenticator::answer($request->credential());
    }
}

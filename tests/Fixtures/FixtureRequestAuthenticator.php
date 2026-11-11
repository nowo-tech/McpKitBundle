<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures;

use Nowo\McpKitBundle\Http\McpHttpRequest;
use Nowo\McpKitBundle\Security\McpAuthFailure;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Security\McpRequestAuthenticatorInterface;

/**
 * Test authenticator on the framework-free port (DDD host): the same throwaway tokens as {@see FixtureMcpAuthenticator}.
 */
final class FixtureRequestAuthenticator implements McpRequestAuthenticatorInterface
{
    /** @var list<McpHttpRequest> */
    public array $seen = [];

    public function authenticateRequest(McpHttpRequest $request): McpPrincipal|McpAuthFailure
    {
        $this->seen[] = $request;

        return FixtureMcpAuthenticator::answer($request->credential());
    }
}

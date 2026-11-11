<?php

declare(strict_types=1);

namespace App\Mcp;

use Nowo\McpKitBundle\Http\McpHttpRequest;
use Nowo\McpKitBundle\Security\McpAuthFailure;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Security\McpRequestAuthenticatorInterface;

/**
 * The bundle's authenticator hook: the same demo tokens as the firewall, answered as a principal for this request.
 */
final readonly class DemoMcpAuthenticator implements McpRequestAuthenticatorInterface
{
    public function __construct(
        private DemoUserProvider $users,
    ) {
    }

    public function authenticateRequest(McpHttpRequest $request): McpPrincipal|McpAuthFailure
    {
        $credential = $request->credential();
        $token      = DemoTokens::find($credential);
        if ($token === null) {
            return McpAuthFailure::Invalid;
        }

        return new McpPrincipal(
            $this->users->loadUserByIdentifier($token['user']),
            substr(hash('sha256', (string) $credential), 0, 12),
            $token['name'],
            $token['destructive'],
            $token['grants'],
        );
    }
}

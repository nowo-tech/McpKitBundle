<?php

declare(strict_types=1);

namespace App\Mcp;

use Nowo\McpKitBundle\Security\McpAuthenticatorInterface;
use Nowo\McpKitBundle\Security\McpAuthFailure;
use Nowo\McpKitBundle\Security\McpCredential;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Symfony\Component\HttpFoundation\Request;

/**
 * The bundle's authenticator hook: the same demo tokens as the firewall, answered as a principal for this request.
 */
final readonly class DemoMcpAuthenticator implements McpAuthenticatorInterface
{
    public function __construct(
        private DemoUserProvider $users,
    ) {
    }

    public function authenticate(Request $request): McpPrincipal|McpAuthFailure
    {
        $credential = McpCredential::fromRequest($request);
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

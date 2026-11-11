<?php

declare(strict_types=1);

namespace App\Security;

use App\Mcp\DemoTokens;
use Nowo\McpKitBundle\Security\McpCredential;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Firewall authenticator of the `mcp` firewall: a known bearer token makes its person the security user. A bad token
 * passes through with no user, so the MCP endpoint answers its own 401.
 */
final class DemoTokenAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): bool
    {
        return McpCredential::fromHeader($request->headers->get('Authorization')) !== null;
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $token = DemoTokens::find(McpCredential::fromHeader($request->headers->get('Authorization')));
        if ($token === null) {
            throw new AuthenticationException('Unknown token.');
        }

        return new SelfValidatingPassport(new UserBadge($token['user']));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}

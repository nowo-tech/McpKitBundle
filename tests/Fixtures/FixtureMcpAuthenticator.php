<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures;

use Nowo\McpKitBundle\Security\McpAuthenticatorInterface;
use Nowo\McpKitBundle\Security\McpAuthFailure;
use Nowo\McpKitBundle\Security\McpCredential;
use Nowo\McpKitBundle\Security\McpPrincipal;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Test authenticator on the deprecated HttpFoundation port: throwaway tokens only.
 */
final class FixtureMcpAuthenticator implements McpAuthenticatorInterface
{
    /** Token => user identifier, shared with the firewall authenticator. */
    public const USERS = ['tok-ada' => 'ada', 'tok-grace' => 'grace', 'tok-mallory' => 'ada'];

    public function authenticate(Request $request): McpPrincipal|McpAuthFailure
    {
        return self::answer(McpCredential::fromHeader($request->headers->get('Authorization')));
    }

    /**
     * The answer for a credential, shared by the deprecated port and its {@see FixtureRequestAuthenticator} twin.
     */
    public static function answer(?string $credential): McpPrincipal|McpAuthFailure
    {
        return match ($credential) {
            'tok-ada'   => new McpPrincipal(new InMemoryUser('ada', ''), 'id-ada', 'Ada read', false, ['demo' => 'read']),
            'tok-grace' => new McpPrincipal(new InMemoryUser('grace', ''), 'id-grace', 'Grace full', true, ['demo' => 'full']),
            // The firewall says "ada" but the hook says "grace": the bundle must refuse.
            'tok-mallory'  => new McpPrincipal(new InMemoryUser('grace', ''), 'id-mallory', 'Mismatch'),
            'tok-disabled' => McpAuthFailure::Disabled,
            'tok-boom'     => throw new RuntimeException('secret detail bt_should_not_leak'),
            default        => McpAuthFailure::Invalid,
        };
    }
}

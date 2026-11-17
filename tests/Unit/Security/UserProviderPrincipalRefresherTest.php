<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Security;

use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Security\UserProviderPrincipalRefresher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;

#[CoversClass(UserProviderPrincipalRefresher::class)]
final class UserProviderPrincipalRefresherTest extends TestCase
{
    #[Test]
    public function itRefreshesTheUserAndKeepsTheRest(): void
    {
        $context   = new stdClass();
        $principal = new McpPrincipal(new InMemoryUser('grace', ''), 'id-grace', 'Grace', true, ['demo' => 'full'], $context);

        $refreshed = (new UserProviderPrincipalRefresher(new InMemoryUserProvider(['grace' => ['password' => '', 'roles' => ['ROLE_X']]])))->refresh($principal);

        self::assertNotSame($principal->user, $refreshed->user);
        self::assertInstanceOf(InMemoryUser::class, $refreshed->user);
        self::assertSame(['ROLE_X'], $refreshed->user->getRoles());
        self::assertSame('id-grace', $refreshed->tokenId);
        self::assertSame('Grace', $refreshed->tokenName);
        self::assertTrue($refreshed->destructiveAllowed);
        self::assertSame(['demo' => 'full'], $refreshed->grants);
        self::assertSame($context, $refreshed->hostContext);
    }

    #[Test]
    public function withoutAProviderOrASecurityUserThePrincipalIsKept(): void
    {
        $principal = new McpPrincipal(new InMemoryUser('grace', ''), 'id', 'n');
        self::assertSame($principal, (new UserProviderPrincipalRefresher())->refresh($principal));

        $plain = new McpPrincipal(new stdClass(), 'id', 'n');
        self::assertSame($plain, (new UserProviderPrincipalRefresher(new InMemoryUserProvider()))->refresh($plain));
    }
}

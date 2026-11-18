<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Security;

use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Security\UserProviderPrincipalRefresher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use stdClass;
use Stringable;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

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
    public function aDifferentRefreshedUserIsRefused(): void
    {
        $principal = new McpPrincipal(new InMemoryUser('grace', ''), 'id-grace', 'Grace');
        $logger    = new class extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };

        // Another identifier, same class.
        $other = (new UserProviderPrincipalRefresher($this->providerReturning(new InMemoryUser('ada', '')), $logger))->refresh($principal);
        self::assertSame($principal, $other);

        // Same identifier, another class.
        $impostor = new class implements UserInterface {
            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'grace';
            }
        };
        self::assertSame($principal, (new UserProviderPrincipalRefresher($this->providerReturning($impostor), $logger))->refresh($principal));
        self::assertCount(2, $logger->messages);
        self::assertSame("MCP: the refreshed user is not the principal's user; auditing the original principal.", $logger->messages[0]);
    }

    /**
     * @return UserProviderInterface<UserInterface>
     */
    private function providerReturning(UserInterface $user): UserProviderInterface
    {
        $provider = $this->createStub(UserProviderInterface::class);
        $provider->method('refreshUser')->willReturn($user);

        return $provider;
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

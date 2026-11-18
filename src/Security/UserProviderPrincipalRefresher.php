<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Default {@see McpPrincipalRefresherInterface}: reloads `$user` through the firewall's user provider (`refreshUser()`,
 * Symfony's `security.user_providers`) when it is a {@see UserInterface} and a provider is known; else the principal is
 * returned as it is. `$hostContext` is kept: a host that puts entities there sets its own `hooks.principal_refresher`.
 * The refreshed user is accepted only when it has the same class and the same `getUserIdentifier()` as the original
 * (audit SZ-L2); otherwise the mismatch is logged and the original principal is kept.
 *
 * Stateless (FrankenPHP worker, kernel reset off).
 */
final readonly class UserProviderPrincipalRefresher implements McpPrincipalRefresherInterface
{
    /**
     * Creates the refresher.
     *
     * @param UserProviderInterface<UserInterface>|null $userProvider the firewall's user provider; null: no refresh
     * @param LoggerInterface $logger logger (constant messages and class names only)
     */
    public function __construct(
        private ?UserProviderInterface $userProvider = null,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * The principal with `$user` refreshed by the user provider.
     *
     * @param McpPrincipal $principal the principal
     *
     * @return McpPrincipal the refreshed principal, or the same one when it cannot be refreshed
     */
    public function refresh(McpPrincipal $principal): McpPrincipal
    {
        if (!$this->userProvider instanceof UserProviderInterface || !$principal->user instanceof UserInterface) {
            return $principal;
        }

        $user = $this->userProvider->refreshUser($principal->user);
        if ($user::class !== $principal->user::class || $user->getUserIdentifier() !== $principal->user->getUserIdentifier()) {
            $this->logger->warning('MCP: the refreshed user is not the principal\'s user; auditing the original principal.', ['bundle' => 'nowo_mcp_kit', 'user_class' => $principal->user::class, 'refreshed_class' => $user::class]);

            return $principal;
        }

        return new McpPrincipal(
            $user,
            $principal->tokenId,
            $principal->tokenName,
            $principal->destructiveAllowed,
            $principal->grants,
            $principal->hostContext,
        );
    }
}

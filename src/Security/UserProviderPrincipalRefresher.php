<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Default {@see McpPrincipalRefresherInterface}: reloads `$user` through the firewall's user provider (`refreshUser()`,
 * Symfony's `security.user_providers`) when it is a {@see UserInterface} and a provider is known; else the principal is
 * returned as it is. `$hostContext` is kept: a host that puts entities there sets its own `hooks.principal_refresher`.
 *
 * Stateless (FrankenPHP worker, kernel reset off).
 */
final readonly class UserProviderPrincipalRefresher implements McpPrincipalRefresherInterface
{
    /**
     * Creates the refresher.
     *
     * @param UserProviderInterface<UserInterface>|null $userProvider the firewall's user provider; null: no refresh
     */
    public function __construct(
        private ?UserProviderInterface $userProvider = null,
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

        return new McpPrincipal(
            $this->userProvider->refreshUser($principal->user),
            $principal->tokenId,
            $principal->tokenName,
            $principal->destructiveAllowed,
            $principal->grants,
            $principal->hostContext,
        );
    }
}

<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Checks that the firewall stands behind the principal: the security user is the principal's user and the request went
 * through a secured firewall. Tools read access through the security token, so a principal the firewall does not stand
 * behind would be judged as someone else, or as nobody.
 */
final readonly class McpFirewallGuard
{
    /**
     * Creates the guard.
     *
     * @param bool $enabled whether to check (`firewall_check`)
     * @param TokenStorageInterface|null $tokenStorage token storage, or null without the security component
     * @param Security|null $security security helper (SecurityBundle) to read the firewall config, or null to fall back to
     *                                the `_firewall_context` request attribute
     */
    public function __construct(
        private bool $enabled,
        private ?TokenStorageInterface $tokenStorage = null,
        private ?Security $security = null,
    ) {
    }

    /**
     * Tells whether the request's security user is the principal's user, behind a secured firewall.
     *
     * @param Request $request the MCP request
     * @param McpPrincipal $principal the authenticated principal
     *
     * @return bool true when trusted (always true with the check off)
     */
    public function isTrusted(Request $request, McpPrincipal $principal): bool
    {
        if (!$this->enabled) {
            return true;
        }
        $securityUser = $this->tokenStorage?->getToken()?->getUser();
        if (!$securityUser instanceof UserInterface || !$this->isSameUser($securityUser, $principal->user)) {
            return false;
        }

        if ($this->security instanceof Security) {
            return $this->security->getFirewallConfig($request)?->isSecurityEnabled() === true;
        }

        return $request->attributes->has('_firewall_context');
    }

    /**
     * Compares the security user with the principal's user.
     *
     * @param UserInterface $securityUser the firewall's user
     * @param object $user the principal's user
     *
     * @return bool true when they are the same person
     */
    private function isSameUser(UserInterface $securityUser, object $user): bool
    {
        if ($securityUser === $user) {
            return true;
        }

        return $user instanceof UserInterface && $user->getUserIdentifier() === $securityUser->getUserIdentifier();
    }
}

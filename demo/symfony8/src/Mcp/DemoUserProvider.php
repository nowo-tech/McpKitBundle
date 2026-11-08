<?php

declare(strict_types=1);

namespace App\Mcp;

use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Demo users (no database). Each demo token belongs to one of them.
 *
 * @implements UserProviderInterface<InMemoryUser>
 */
final class DemoUserProvider implements UserProviderInterface
{
    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === InMemoryUser::class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if (!in_array($identifier, ['ada', 'grace'], true)) {
            throw new UserNotFoundException();
        }

        return new InMemoryUser($identifier, null, ['ROLE_USER']);
    }
}

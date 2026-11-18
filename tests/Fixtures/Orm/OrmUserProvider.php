<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Orm;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Host user provider over the entity manager (as Symfony's entity provider): refreshUser() reloads a managed user.
 *
 * @implements UserProviderInterface<OrmUser>
 */
final class OrmUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly FixtureOrm $orm,
    ) {
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof OrmUser) {
            throw new UnsupportedUserException('Not an OrmUser.');
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === OrmUser::class;
    }

    public function loadUserByIdentifier(string $identifier): OrmUser
    {
        $user = $this->orm->em->getRepository(OrmUser::class)->findOneBy(['username' => $identifier]);
        if (!$user instanceof OrmUser) {
            throw new UserNotFoundException();
        }

        return $user;
    }
}

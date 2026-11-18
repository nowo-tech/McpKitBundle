<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Orm;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A host user entity: the principal's `$user` in the write-unit audit tests.
 */
#[ORM\Entity]
#[ORM\Table(name: 'orm_user')]
class OrmUser implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 64, unique: true)]
        public string $username,
    ) {
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->username !== '' ? $this->username : 'unknown';
    }
}

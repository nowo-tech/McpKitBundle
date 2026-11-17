<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Orm;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;

/**
 * The host's Doctrine registry over {@see FixtureOrm}: one connection, one entity manager.
 */
final class OrmManagerRegistry implements ManagerRegistry
{
    public function __construct(
        private readonly FixtureOrm $orm,
    ) {
    }

    public function getDefaultConnectionName(): string
    {
        return 'default';
    }

    public function getConnection(?string $name = null): Connection
    {
        return $this->orm->em->getConnection();
    }

    public function getConnections(): array
    {
        return ['default' => $this->orm->em->getConnection()];
    }

    public function getConnectionNames(): array
    {
        return ['default' => 'doctrine.dbal.default_connection'];
    }

    public function getDefaultManagerName(): string
    {
        return 'default';
    }

    public function getManager(?string $name = null): ObjectManager
    {
        return $this->orm->em;
    }

    public function getManagers(): array
    {
        return ['default' => $this->orm->em];
    }

    public function resetManager(?string $name = null): ObjectManager
    {
        return $this->orm->em;
    }

    public function getManagerNames(): array
    {
        return ['default' => 'doctrine.orm.default_entity_manager'];
    }

    public function getRepository(string $persistentObject, ?string $persistentManagerName = null): ObjectRepository
    {
        return $this->orm->em->getRepository($persistentObject);
    }

    public function getManagerForClass(string $class): ObjectManager
    {
        return $this->orm->em;
    }
}

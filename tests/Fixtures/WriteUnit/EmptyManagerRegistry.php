<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\WriteUnit;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use LogicException;

/**
 * A Doctrine registry without connections or managers: the real write unit runs and replays, with nothing to repair.
 */
final class EmptyManagerRegistry implements ManagerRegistry
{
    public function getDefaultConnectionName(): string
    {
        return 'default';
    }

    public function getConnection(?string $name = null): object
    {
        throw new LogicException('No connection.');
    }

    public function getConnections(): array
    {
        return [];
    }

    public function getConnectionNames(): array
    {
        return [];
    }

    public function getDefaultManagerName(): string
    {
        return 'default';
    }

    public function getManager(?string $name = null): ObjectManager
    {
        throw new LogicException('No manager.');
    }

    public function getManagers(): array
    {
        return [];
    }

    public function resetManager(?string $name = null): ObjectManager
    {
        throw new LogicException('No manager.');
    }

    public function getManagerNames(): array
    {
        return [];
    }

    public function getRepository(string $persistentObject, ?string $persistentManagerName = null): ObjectRepository
    {
        throw new LogicException('No repository.');
    }

    public function getManagerForClass(string $class): ?ObjectManager
    {
        return null;
    }
}

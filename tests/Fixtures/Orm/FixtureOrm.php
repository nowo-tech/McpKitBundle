<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Orm;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

use function sys_get_temp_dir;

use const PHP_VERSION_ID;

/**
 * One in-memory SQLite entity manager shared by the host fixtures (the host's default entity manager), with the user
 * "grace".
 */
final class FixtureOrm
{
    public readonly EntityManager $em;

    public function __construct()
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__], true);
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        } else {
            $config->setProxyDir(sys_get_temp_dir() . '/nowo-mcp-kit-proxies');
            $config->setProxyNamespace('NowoMcpKitTestProxies');
            $config->setAutoGenerateProxyClasses(true);
        }
        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        (new SchemaTool($this->em))->createSchema([$this->em->getClassMetadata(OrmUser::class), $this->em->getClassMetadata(OrmAuditRow::class)]);
        $this->em->persist(new OrmUser('grace'));
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * Audit rows written, as [tool, outcome, attempts] tuples (straight from the database).
     *
     * @return list<array{string, string, int}>
     */
    public function auditRows(): array
    {
        $rows = [];
        foreach ($this->em->getConnection()->fetchAllAssociative('SELECT tool, outcome, attempts FROM orm_audit_row ORDER BY id') as $row) {
            $rows[] = [(string) $row['tool'], (string) $row['outcome'], (int) $row['attempts']];
        }

        return $rows;
    }

    /**
     * Whether the unit of work still holds scheduled work (a stale row queued in the shared manager).
     */
    public function hasQueuedWork(): bool
    {
        $unitOfWork = $this->em->getUnitOfWork();

        return $unitOfWork->getScheduledEntityInsertions() !== [] || $unitOfWork->getScheduledEntityUpdates() !== [];
    }
}

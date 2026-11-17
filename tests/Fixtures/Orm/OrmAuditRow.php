<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Orm;

use Doctrine\ORM\Mapping as ORM;

/**
 * A host audit row linking the user entity (no cascade), as Beacon's McpToolCallAuditListener does.
 */
#[ORM\Entity]
#[ORM\Table(name: 'orm_audit_row')]
class OrmAuditRow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: OrmUser::class)]
        #[ORM\JoinColumn(nullable: false)]
        public OrmUser $user,
        #[ORM\Column(length: 64)]
        public string $tool,
        #[ORM\Column(length: 32)]
        public string $outcome,
        #[ORM\Column]
        public int $attempts,
    ) {
    }
}

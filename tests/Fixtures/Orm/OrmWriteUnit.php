<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Orm;

use Nowo\McpKitBundle\Tests\Fixtures\WriteUnit\LedgerWriteUnit;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Nowo\TransactionDoctrine\WriteUnit\WriteUnit;
use Nowo\TransactionDoctrine\WriteUnit\WriteUnitPolicy;

/**
 * transaction-doctrine's real WriteUnit over the host entity manager: between attempts (and before a committed
 * conflict) it clears that manager, which detaches the principal's user.
 */
final class OrmWriteUnit implements LedgerWriteUnit
{
    private readonly CommitLedger $ledger;

    private readonly WriteUnit $inner;

    public function __construct(OrmManagerRegistry $registry)
    {
        $this->ledger = new CommitLedger();
        $this->inner  = new WriteUnit($registry, new WriteUnitPolicy(3, 0, 0, 0), null, null, null, null, $this->ledger);
    }

    public function ledger(): CommitLedger
    {
        return $this->ledger;
    }

    public function run(callable $work): mixed
    {
        return $this->inner->run($work);
    }
}

<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\WriteUnit;

use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Nowo\TransactionDoctrine\WriteUnit\WriteUnit;
use Nowo\TransactionDoctrine\WriteUnit\WriteUnitPolicy;

/**
 * transaction-doctrine's real WriteUnit (3 attempts, no pause, commit detection through a ledger) on a registry without
 * connections; counts the units it ran.
 */
final class FixtureWriteUnit implements LedgerWriteUnit
{
    public readonly CommitLedger $ledger;

    public int $units = 0;

    private readonly WriteUnit $inner;

    public function __construct()
    {
        $this->ledger = new CommitLedger();
        $this->inner  = new WriteUnit(new EmptyManagerRegistry(), new WriteUnitPolicy(3, 0, 0, 0), null, null, null, null, $this->ledger);
    }

    public function ledger(): CommitLedger
    {
        return $this->ledger;
    }

    public function run(callable $work): mixed
    {
        ++$this->units;

        return $this->inner->run($work);
    }
}

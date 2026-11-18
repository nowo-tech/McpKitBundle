<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\WriteUnit;

use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Nowo\TransactionDoctrine\WriteUnit\WriteUnitInterface;

/**
 * A test write unit whose commit ledger a tool can feed (to simulate a commit before a conflict).
 */
interface LedgerWriteUnit extends WriteUnitInterface
{
    public function ledger(): CommitLedger;
}

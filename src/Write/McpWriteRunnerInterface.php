<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Write;

use Nowo\McpKitBundle\Bridge\TransactionDoctrine\TransactionDoctrineWriteRunner;
use Throwable;

/**
 * Runs the execution of one additive or destructive tool as a write unit: the tool is replayed after a lock conflict,
 * not the HTTP request, so the precheck, the write budget and the audit event happen once per tool call. Read-only tools
 * never go through it.
 *
 * The bundle ships one implementation, {@see TransactionDoctrineWriteRunner}
 * (option `write_unit.enabled`); a host may wire its own through the same port.
 */
interface McpWriteRunnerInterface
{
    /**
     * Runs the work, replaying it after a lock conflict.
     *
     * @template T
     *
     * @param callable(int): T $work the tool execution; called with the attempt number (1-based)
     *
     * @throws McpWriteConflict when the conflict outlived the retries, or the failed attempt had already committed
     * @throws Throwable any other failure of the work, unchanged
     *
     * @return T what the work returned
     */
    public function run(callable $work): mixed;
}

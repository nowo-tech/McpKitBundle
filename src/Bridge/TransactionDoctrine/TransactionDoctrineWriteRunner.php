<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Bridge\TransactionDoctrine;

use Nowo\McpKitBundle\Write\McpWriteConflict;
use Nowo\McpKitBundle\Write\McpWriteRunnerInterface;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\WriteUnit\ConcurrencyConflict;
use Nowo\TransactionDoctrine\WriteUnit\WriteUnitInterface;
use Throwable;

/**
 * {@see McpWriteRunnerInterface} on nowo-tech/transaction-doctrine (^1.3): the work runs inside
 * {@see WriteUnitInterface::run()} and the final {@see ConcurrencyConflict} becomes a {@see McpWriteConflict} with the
 * semantics of transaction-doctrine's own responder: the status and `Retry-After` of {@see ConflictResponsePolicy}, and
 * no retry hint when `committed` is true.
 *
 * Stateless (FrankenPHP worker, kernel reset off): nothing of a call is kept on the service.
 */
final readonly class TransactionDoctrineWriteRunner implements McpWriteRunnerInterface
{
    /**
     * Creates the runner.
     *
     * @param WriteUnitInterface $writeUnit transaction-doctrine's write unit (its `max_attempts` and backoff apply)
     * @param ConflictResponsePolicy|null $policy the platform's conflict answer; null: 409 and the conflict's own hint
     */
    public function __construct(
        private WriteUnitInterface $writeUnit,
        private ?ConflictResponsePolicy $policy = null,
    ) {
    }

    /**
     * Runs the work inside the write unit.
     *
     * @template T
     *
     * @param callable(int): T $work the tool execution; called with the attempt number (1-based)
     *
     * @throws McpWriteConflict when the write unit gave up
     * @throws Throwable any other failure of the work, unchanged
     *
     * @return T what the work returned
     */
    public function run(callable $work): mixed
    {
        try {
            return $this->writeUnit->run(static fn (int $attempt, ?Throwable $previous): mixed => $work($attempt));
        } catch (ConcurrencyConflict $conflict) {
            $hint = $conflict->retryAfterSeconds();

            throw new McpWriteConflict($conflict->attempts, $this->policy?->retryAfter($hint) ?? $hint, $conflict->committed, $this->policy->status ?? McpWriteConflict::DEFAULT_STATUS, $conflict);
        }
    }
}

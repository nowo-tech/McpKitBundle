<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Bridge\TransactionDoctrine;

use LogicException;
use Nowo\McpKitBundle\Bridge\TransactionDoctrine\TransactionDoctrineWriteRunner;
use Nowo\McpKitBundle\Write\McpWriteConflict;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\TransientFailureKind;
use Nowo\TransactionDoctrine\WriteUnit\ConcurrencyConflict;
use Nowo\TransactionDoctrine\WriteUnit\WriteUnitInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

use function array_pop;

#[CoversClass(TransactionDoctrineWriteRunner::class)]
#[CoversClass(McpWriteConflict::class)]
final class TransactionDoctrineWriteRunnerTest extends TestCase
{
    /**
     * A write unit that calls the work with the given attempts, then throws the given failure (if any).
     *
     * @param list<int> $attempts
     */
    private function unit(array $attempts, ?Throwable $failure = null): WriteUnitInterface
    {
        return new class($attempts, $failure) implements WriteUnitInterface {
            /**
             * @param list<int> $attempts
             */
            public function __construct(private readonly array $attempts, private readonly ?Throwable $failure)
            {
            }

            public function run(callable $work): mixed
            {
                if ($this->failure instanceof Throwable) {
                    throw $this->failure;
                }
                $attempts = $this->attempts;
                $last     = (int) array_pop($attempts);
                foreach ($attempts as $attempt) {
                    $work($attempt, null);
                }

                return $work($last, null);
            }
        };
    }

    #[Test]
    public function itPassesTheAttemptAndReturnsTheResult(): void
    {
        $seen   = [];
        $runner = new TransactionDoctrineWriteRunner($this->unit([1, 2]));

        $result = $runner->run(static function (int $attempt) use (&$seen): string {
            $seen[] = $attempt;

            return 'r' . $attempt;
        });

        self::assertSame('r2', $result);
        self::assertSame([1, 2], $seen);
    }

    #[Test]
    public function anotherFailurePassesUnchanged(): void
    {
        $failure = new LogicException('bug');
        $runner  = new TransactionDoctrineWriteRunner($this->unit([], $failure));

        try {
            $runner->run(static fn (int $attempt): int => $attempt);
            self::fail('expected the failure');
        } catch (LogicException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    #[Test]
    public function aFinalConflictFollowsTheConflictPolicy(): void
    {
        $conflict = new ConcurrencyConflict(3, TransientFailureKind::Deadlock, 2, new RuntimeException('deadlock'));
        $runner   = new TransactionDoctrineWriteRunner($this->unit([], $conflict), new ConflictResponsePolicy(503, 9));

        try {
            $runner->run(static fn (int $attempt): int => $attempt);
            self::fail('expected a conflict');
        } catch (McpWriteConflict $caught) {
            self::assertSame(3, $caught->attempts);
            self::assertSame(2, $caught->retryAfterSeconds, "the unit's own hint wins, as in transaction-doctrine's responder");
            self::assertFalse($caught->committed);
            self::assertSame(503, $caught->status);
            self::assertSame($conflict, $caught->getPrevious());
        }
    }

    #[Test]
    public function withoutAHintThePolicyValueApplies(): void
    {
        $conflict = new ConcurrencyConflict(3, TransientFailureKind::LockWaitTimeout, null, new RuntimeException('timeout'));
        $runner   = new TransactionDoctrineWriteRunner($this->unit([], $conflict), new ConflictResponsePolicy(409, 9));

        try {
            $runner->run(static fn (int $attempt): int => $attempt);
            self::fail('expected a conflict');
        } catch (McpWriteConflict $caught) {
            self::assertSame(9, $caught->retryAfterSeconds);
            self::assertSame(409, $caught->status);
        }
    }

    #[Test]
    public function withoutAPolicyTheAnswerIs409WithTheDefaultHint(): void
    {
        $conflict = new ConcurrencyConflict(3, TransientFailureKind::Deadlock, null, new RuntimeException('deadlock'));
        $runner   = new TransactionDoctrineWriteRunner($this->unit([], $conflict));

        try {
            $runner->run(static fn (int $attempt): int => $attempt);
            self::fail('expected a conflict');
        } catch (McpWriteConflict $caught) {
            self::assertSame(McpWriteConflict::DEFAULT_RETRY_AFTER, $caught->retryAfterSeconds);
            self::assertSame(409, $caught->status);
        }
    }

    #[Test]
    public function aCommittedConflictHasNoRetryHint(): void
    {
        $conflict = new ConcurrencyConflict(1, TransientFailureKind::Deadlock, null, new RuntimeException('deadlock'), true);
        $runner   = new TransactionDoctrineWriteRunner($this->unit([], $conflict), new ConflictResponsePolicy());

        try {
            $runner->run(static fn (int $attempt): int => $attempt);
            self::fail('expected a conflict');
        } catch (McpWriteConflict $caught) {
            self::assertTrue($caught->committed);
            self::assertNull($caught->retryAfterSeconds);
            self::assertSame('CONFLICT_002', $caught->errorCode());
        }
    }
}

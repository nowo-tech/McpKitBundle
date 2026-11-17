<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Write;

use RuntimeException;
use Throwable;

use function max;

/**
 * A write tool still met a lock conflict after its write unit gave up. The message is constant and client-safe.
 *
 * Retryable ({@see $committed} false): nothing of the call was committed, the client may call again after
 * {@see $retryAfterSeconds}. Committed ({@see $committed} true): the failed attempt had already committed part of its
 * writes, so the call was not replayed and a blind retry could repeat them; no retry hint.
 */
final class McpWriteConflict extends RuntimeException
{
    public const MESSAGE = 'The write met a database lock conflict.';

    /** `error.data.code` of a retryable conflict. */
    public const RETRYABLE_CODE = 'CONFLICT_001';

    /** `error.data.code` of a conflict after part of the call was committed (no retry hint). */
    public const COMMITTED_CODE = 'CONFLICT_002';

    /** HTTP status when no conflict response policy is known. */
    public const DEFAULT_STATUS = 409;

    /** Retry hint when no conflict response policy is known. */
    public const DEFAULT_RETRY_AFTER = 1;

    /** Seconds after which a retry is sensible (0 or more); always null when {@see $committed} is true. */
    public readonly ?int $retryAfterSeconds;

    /**
     * Creates the conflict.
     *
     * @param int $attempts attempts the write unit made (1 or more)
     * @param int|null $retryAfterSeconds retry hint; ignored when committed, {@see DEFAULT_RETRY_AFTER} when null
     * @param bool $committed whether part of the call was already committed
     * @param int $status HTTP status of the answer (409, or 503 for hosts that chose it)
     * @param Throwable|null $previous the write unit's failure
     */
    public function __construct(
        public readonly int $attempts,
        ?int $retryAfterSeconds,
        public readonly bool $committed = false,
        public readonly int $status = self::DEFAULT_STATUS,
        ?Throwable $previous = null,
    ) {
        parent::__construct(self::MESSAGE, 0, $previous);
        $this->retryAfterSeconds = $committed ? null : max(0, $retryAfterSeconds ?? self::DEFAULT_RETRY_AFTER);
    }

    /**
     * The `error.data.code` of the answer.
     *
     * @return string `CONFLICT_001` or `CONFLICT_002`
     */
    public function errorCode(): string
    {
        return $this->committed ? self::COMMITTED_CODE : self::RETRYABLE_CODE;
    }
}

<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Write;

use Nowo\McpKitBundle\Protocol\JsonRpcError;
use Nowo\McpKitBundle\Protocol\McpProtocol;
use Nowo\McpKitBundle\Write\McpWriteConflict;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(McpWriteConflict::class)]
#[CoversClass(JsonRpcError::class)]
final class McpWriteConflictTest extends TestCase
{
    #[Test]
    public function aRetryableConflictKeepsItsHint(): void
    {
        $previous = new RuntimeException('deadlock');
        $conflict = new McpWriteConflict(3, 4, false, 503, $previous);

        self::assertSame(3, $conflict->attempts);
        self::assertSame(4, $conflict->retryAfterSeconds);
        self::assertFalse($conflict->committed);
        self::assertSame(503, $conflict->status);
        self::assertSame('CONFLICT_001', $conflict->errorCode());
        self::assertSame(McpWriteConflict::MESSAGE, $conflict->getMessage());
        self::assertSame($previous, $conflict->getPrevious());

        $error = JsonRpcError::writeConflict($conflict);
        self::assertSame(McpProtocol::WRITE_CONFLICT, $error->errorCode);
        self::assertSame(503, $error->status);
        self::assertSame(['code' => 'CONFLICT_001', 'retry_after' => 4], $error->data);
        self::assertSame(['Retry-After' => '4'], $error->headers);
        self::assertNull($error->legacyStatus);
    }

    #[Test]
    public function aMissingOrNegativeHintBecomesASafeValue(): void
    {
        self::assertSame(McpWriteConflict::DEFAULT_RETRY_AFTER, (new McpWriteConflict(1, null))->retryAfterSeconds);
        self::assertSame(0, (new McpWriteConflict(1, -5))->retryAfterSeconds);
        self::assertSame(McpWriteConflict::DEFAULT_STATUS, (new McpWriteConflict(1, null))->status);
    }

    #[Test]
    public function aCommittedConflictHasNoRetryHint(): void
    {
        $conflict = new McpWriteConflict(1, 7, true);

        self::assertNull($conflict->retryAfterSeconds);
        self::assertSame('CONFLICT_002', $conflict->errorCode());

    }
}

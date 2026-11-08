<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Protocol;

use Nowo\McpKitBundle\Protocol\JsonRpcError;
use Nowo\McpKitBundle\Protocol\McpEra;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonRpcError::class)]
final class JsonRpcErrorTest extends TestCase
{
    #[Test]
    public function statusDependsOnTheEra(): void
    {
        $notFound = JsonRpcError::methodNotFound();
        self::assertSame(404, $notFound->statusFor(McpEra::Modern));
        self::assertSame(404, $notFound->statusFor(null));
        self::assertSame(200, $notFound->statusFor(McpEra::Legacy));
        self::assertSame(400, JsonRpcError::invalidRequest()->statusFor(McpEra::Legacy));
        self::assertSame(500, JsonRpcError::internal()->statusFor(McpEra::Legacy));
        self::assertSame('Header mismatch: Mcp-Name', JsonRpcError::headerMismatch('Mcp-Name')->getMessage());
        self::assertSame(-32602, JsonRpcError::invalidParamsOk('Unknown tool')->getCode());
    }
}

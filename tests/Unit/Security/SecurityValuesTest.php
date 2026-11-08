<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Security;

use Nowo\McpKitBundle\Security\AlwaysAvailable;
use Nowo\McpKitBundle\Security\DenyingMcpRateLimiter;
use Nowo\McpKitBundle\Security\McpAvailability;
use Nowo\McpKitBundle\Security\McpCredential;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Security\McpRateLimitState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(AlwaysAvailable::class)]
#[CoversClass(DenyingMcpRateLimiter::class)]
#[CoversClass(McpCredential::class)]
#[CoversClass(McpPrincipal::class)]
#[CoversClass(McpRateLimitState::class)]
final class SecurityValuesTest extends TestCase
{
    #[Test]
    public function defaultsDenyBudgetsAndStayAvailable(): void
    {
        $principal = new McpPrincipal(new stdClass(), 'id', 'name');
        $limiter   = new DenyingMcpRateLimiter();

        self::assertFalse($limiter->consume($principal)->accepted);
        self::assertFalse($limiter->consumeWrite($principal, 'tool')->accepted);
        self::assertTrue($limiter->failedTokenState('127.0.0.1')->accepted);
        $limiter->registerFailedToken('127.0.0.1');
        self::assertSame(McpAvailability::Available, (new AlwaysAvailable())->state(Request::create('/mcp')));
        self::assertFalse($principal->destructiveAllowed);
        self::assertSame([], $principal->grants);
        self::assertNull($principal->hostContext);
    }

    #[Test]
    public function theCredentialComesFromTheAuthorizationHeaderOnly(): void
    {
        self::assertNull(McpCredential::fromRequest(Request::create('/mcp?access_token=abc')));
        self::assertSame('abc', McpCredential::fromRequest(Request::create('/mcp', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer   abc '])));
        self::assertSame('abc', McpCredential::fromRequest(Request::create('/mcp', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'abc'])));
        self::assertNull(McpCredential::fromRequest(Request::create('/mcp', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'bearer '])));
    }
}

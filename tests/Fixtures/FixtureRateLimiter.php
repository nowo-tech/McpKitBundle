<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures;

use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Security\McpRateLimiterInterface;
use Nowo\McpKitBundle\Security\McpRateLimitState;

/**
 * Test rate limiter with switches and counters.
 */
final class FixtureRateLimiter implements McpRateLimiterInterface
{
    public bool $refuseRequests = false;
    public bool $refuseWrites   = false;
    public bool $blockIp        = false;

    /** @var list<string> */
    public array $consumed = [];

    /** @var list<string> */
    public array $writes = [];

    /** @var list<string> */
    public array $failedIps = [];

    public function consume(McpPrincipal $principal): McpRateLimitState
    {
        $this->consumed[] = $principal->tokenId;

        return new McpRateLimitState(!$this->refuseRequests, 100, $this->refuseRequests ? 0 : 99, time() + 30);
    }

    public function consumeWrite(McpPrincipal $principal, string $tool): McpRateLimitState
    {
        $this->writes[] = $tool;

        return new McpRateLimitState(!$this->refuseWrites, 30, $this->refuseWrites ? 0 : 29, time() + 45);
    }

    public function failedTokenState(string $clientIp): McpRateLimitState
    {
        return new McpRateLimitState(!$this->blockIp, 10, $this->blockIp ? 0 : 10, time() + 10);
    }

    public function registerFailedToken(string $clientIp): void
    {
        $this->failedIps[] = $clientIp;
    }
}

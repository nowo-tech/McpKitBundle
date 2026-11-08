<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

/**
 * Default rate limiter: refuses every request, so a host cannot go live without real budgets.
 */
final readonly class DenyingMcpRateLimiter implements McpRateLimiterInterface
{
    /**
     * Refuses.
     *
     * @param McpPrincipal $principal the token
     *
     * @return McpRateLimitState a refusal
     */
    public function consume(McpPrincipal $principal): McpRateLimitState
    {
        return new McpRateLimitState(false, 0, 0, 0);
    }

    /**
     * Refuses.
     *
     * @param McpPrincipal $principal the token
     * @param string $tool the tool name
     *
     * @return McpRateLimitState a refusal
     */
    public function consumeWrite(McpPrincipal $principal, string $tool): McpRateLimitState
    {
        return new McpRateLimitState(false, 0, 0, 0);
    }

    /**
     * Accepts: the refusal comes from {@see self::consume()}.
     *
     * @param string $clientIp the client IP
     *
     * @return McpRateLimitState an acceptance
     */
    public function failedTokenState(string $clientIp): McpRateLimitState
    {
        return new McpRateLimitState(true, 0, 0, 0);
    }

    /**
     * Does nothing.
     *
     * @param string $clientIp the client IP
     */
    public function registerFailedToken(string $clientIp): void
    {
    }
}

<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

/**
 * The host's budgets: one per token (every authenticated request), a write budget per token (before each additive or
 * destructive tool), and a failed-token limit per client IP.
 */
interface McpRateLimiterInterface
{
    /**
     * Consumes one request of the token's budget.
     *
     * @param McpPrincipal $principal the token
     *
     * @return McpRateLimitState the answer
     */
    public function consume(McpPrincipal $principal): McpRateLimitState;

    /**
     * Consumes one write of the token's write budget, before the tool runs.
     *
     * @param McpPrincipal $principal the token
     * @param string $tool the tool name
     *
     * @return McpRateLimitState the answer
     */
    public function consumeWrite(McpPrincipal $principal, string $tool): McpRateLimitState;

    /**
     * Peeks the failed-token limit of an IP, before authenticating.
     *
     * @param string $clientIp the client IP
     *
     * @return McpRateLimitState the answer
     */
    public function failedTokenState(string $clientIp): McpRateLimitState;

    /**
     * Counts one failed token for an IP.
     *
     * @param string $clientIp the client IP
     */
    public function registerFailedToken(string $clientIp): void;
}

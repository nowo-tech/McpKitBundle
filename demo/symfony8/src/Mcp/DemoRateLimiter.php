<?php

declare(strict_types=1);

namespace App\Mcp;

use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Security\McpRateLimiterInterface;
use Nowo\McpKitBundle\Security\McpRateLimitState;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Fixed one-minute windows in the app cache: 100 requests and 30 writes per token, 20 failed tokens per IP. Storage is
 * the cache pool, so nothing is kept in the service between requests (worker mode).
 */
final readonly class DemoRateLimiter implements McpRateLimiterInterface
{
    public function __construct(
        private CacheItemPoolInterface $cacheApp,
    ) {
    }

    public function consume(McpPrincipal $principal): McpRateLimitState
    {
        return $this->hit('req.' . $principal->tokenId, 100);
    }

    public function consumeWrite(McpPrincipal $principal, string $tool): McpRateLimitState
    {
        return $this->hit('write.' . $principal->tokenId, 30);
    }

    public function failedTokenState(string $clientIp): McpRateLimitState
    {
        return $this->state('fail.' . hash('sha256', $clientIp), 20, false);
    }

    public function registerFailedToken(string $clientIp): void
    {
        $this->hit('fail.' . hash('sha256', $clientIp), 20);
    }

    private function hit(string $key, int $limit): McpRateLimitState
    {
        return $this->state($key, $limit, true);
    }

    private function state(string $key, int $limit, bool $count): McpRateLimitState
    {
        $window = intdiv(time(), 60);
        $item   = $this->cacheApp->getItem('demo_mcp_rate.' . $key . '.' . $window);
        $used   = $item->isHit() ? (int) $item->get() : 0;
        if ($count) {
            ++$used;
            $item->set($used)->expiresAfter(120);
            $this->cacheApp->save($item);
        }
        $resetAt = ($window + 1) * 60;

        return $count
            ? new McpRateLimitState($used <= $limit, $limit, max(0, $limit - $used), $resetAt)
            : new McpRateLimitState($used < $limit, $limit, max(0, $limit - $used), $resetAt);
    }
}

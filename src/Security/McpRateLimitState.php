<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

/**
 * A rate-limit answer, sent as `X-RateLimit-Limit`, `-Remaining` and `-Reset` (Unix seconds); `Retry-After` when refused.
 */
final readonly class McpRateLimitState
{
    /**
     * Creates the state.
     *
     * @param bool $accepted whether the request may go on
     * @param int $limit the limit
     * @param int $remaining what is left
     * @param int $resetAt Unix time of the next refill
     */
    public function __construct(
        public bool $accepted,
        public int $limit,
        public int $remaining,
        public int $resetAt,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tool;

use Nowo\McpKitBundle\Context\McpCallContext;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Symfony\Component\HttpFoundation\Request;

use function is_string;

/**
 * One tool call: who, with which validated arguments, in which context.
 */
final readonly class McpToolCall
{
    /**
     * Creates the call.
     *
     * @param McpPrincipal $principal the authenticated principal
     * @param array<string, mixed> $arguments arguments validated against the input schema (JSON objects as arrays)
     * @param McpCallContext $context era, version, client and tool name
     * @param Request $request the HTTP request (behind the firewall)
     */
    public function __construct(
        public McpPrincipal $principal,
        public array $arguments,
        public McpCallContext $context,
        public Request $request,
    ) {
    }

    /**
     * The optional `idempotency_key` argument ({@see McpIdempotencyKey::schema()}), for the host's idempotency store.
     *
     * @return string|null the key, or null when absent
     */
    public function idempotencyKey(): ?string
    {
        $key = $this->arguments[McpIdempotencyKey::ARGUMENT] ?? null;

        return is_string($key) ? $key : null;
    }
}

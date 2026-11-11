<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tool;

use Nowo\McpKitBundle\Context\McpCallContext;
use Nowo\McpKitBundle\Http\McpHttpRequest;
use Nowo\McpKitBundle\Http\McpHttpRequestFactory;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Symfony\Component\HttpFoundation\Request;

use function is_string;

/**
 * One tool call: who, with which validated arguments, in which context.
 */
final readonly class McpToolCall
{
    /** Framework-free view of the HTTP request (method, path, headers, client IP; credentials redacted when logged). */
    public McpHttpRequest $http;

    /**
     * Creates the call.
     *
     * @param McpPrincipal $principal the authenticated principal
     * @param array<string, mixed> $arguments arguments validated against the input schema (JSON objects as arrays)
     * @param McpCallContext $context era, version, client and tool name
     * @param Request $request the HTTP request (behind the firewall); deprecated, read {@see $http} instead
     * @param McpHttpRequest|null $http framework-free view of the request (built from `$request` when null)
     */
    public function __construct(
        public McpPrincipal $principal,
        public array $arguments,
        public McpCallContext $context,
        /** @deprecated since 1.1.0, read {@see $http} (no HttpFoundation type); removed in 2.0.0 */
        public Request $request,
        ?McpHttpRequest $http = null,
    ) {
        $this->http = $http ?? McpHttpRequestFactory::fromRequest($request);
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

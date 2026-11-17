<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Server;

use Nowo\McpKitBundle\Context\McpCallContext;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolInterface;
use Nowo\McpKitBundle\Tool\McpToolResult;
use Symfony\Component\HttpFoundation\Request;

/**
 * One tool call as the work of the write runner (or a single direct run): each attempt gets a fresh {@see McpToolCall}
 * and the context attribute is set again. McpKit's own call data is rebuilt per attempt; the HTTP request, principal
 * and host context are the same objects (no dependency on transaction-doctrine's reset hooks). Counts the attempts for
 * the audit event. Built per call, never kept on a service.
 *
 * @internal
 */
final class McpToolAttempt
{
    private int $attempts = 0;

    /**
     * Creates the work of one call.
     *
     * @param McpToolInterface $tool the tool
     * @param McpPrincipal $principal the principal
     * @param array<string, mixed> $arguments the validated arguments (copied into each attempt's call)
     * @param McpCallContext $context the call context
     * @param Request $request the HTTP request
     */
    public function __construct(
        private readonly McpToolInterface $tool,
        private readonly McpPrincipal $principal,
        private readonly array $arguments,
        private readonly McpCallContext $context,
        private readonly Request $request,
    ) {
    }

    /**
     * Runs one attempt.
     *
     * @param int $attempt attempt number (1-based)
     *
     * @return McpToolResult the tool's result
     */
    public function __invoke(int $attempt): McpToolResult
    {
        $this->attempts = $attempt; // @igor-ignore — a per-call object built by the invoker, never a service
        $this->request->attributes->set(McpCallContext::ATTRIBUTE, $this->context);

        return $this->tool->call(new McpToolCall($this->principal, $this->arguments, $this->context, $this->request, null, $attempt));
    }

    /**
     * How many times the tool ran so far (the last attempt number).
     *
     * @return int 0 before the first attempt
     */
    public function attempts(): int
    {
        return $this->attempts;
    }
}

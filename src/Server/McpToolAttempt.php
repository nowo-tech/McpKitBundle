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
 * One write tool call as the write runner's work: each attempt gets a fresh {@see McpToolCall} built from the call's
 * immutable inputs, and the context attribute is set again, so a replay never sees what a failed attempt built or
 * changed (no dependency on transaction-doctrine's reset hooks). Built per call, never kept on a service.
 *
 * @internal
 */
final readonly class McpToolAttempt
{
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
        private McpToolInterface $tool,
        private McpPrincipal $principal,
        private array $arguments,
        private McpCallContext $context,
        private Request $request,
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
        $this->request->attributes->set(McpCallContext::ATTRIBUTE, $this->context);

        return $this->tool->call(new McpToolCall($this->principal, $this->arguments, $this->context, $this->request, null, $attempt));
    }
}

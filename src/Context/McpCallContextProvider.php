<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Context;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Reads the {@see McpCallContext} of the current main request, for host services that have no request at hand (write
 * event listeners).
 */
final readonly class McpCallContextProvider
{
    /**
     * Creates the provider.
     *
     * @param RequestStack $requestStack request stack
     */
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /**
     * The context of the MCP call in progress.
     *
     * @return McpCallContext|null the context, or null outside an MCP call
     */
    public function current(): ?McpCallContext
    {
        $request = $this->requestStack->getMainRequest();

        return $request instanceof Request ? McpCallContext::fromAttribute($request->attributes->get(McpCallContext::ATTRIBUTE)) : null;
    }
}

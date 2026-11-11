<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

use Nowo\McpKitBundle\Http\McpHttpRequest;

/**
 * The host's switches (an instance setting that turns MCP off, maintenance), with a framework-free view of the request.
 *
 * Replaces {@see McpAvailabilityInterface} (same contract, no HttpFoundation type). When a service implements both, the
 * bundle calls this one.
 */
interface McpRequestAvailabilityInterface
{
    /**
     * Tells whether the endpoint serves this request.
     *
     * @param McpHttpRequest $request the MCP request
     *
     * @return McpAvailability the state
     */
    public function availabilityOf(McpHttpRequest $request): McpAvailability;
}

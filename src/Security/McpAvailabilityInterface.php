<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

use Nowo\McpKitBundle\Http\McpHttpRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * The host's switches: an instance setting that turns MCP off, maintenance.
 *
 * @deprecated since 1.1.0, implement {@see McpRequestAvailabilityInterface} instead (it receives the framework-free
 *             {@see McpHttpRequest}); removed in 2.0.0
 */
interface McpAvailabilityInterface
{
    /**
     * Tells whether the endpoint serves this request.
     *
     * @param Request $request the MCP request
     *
     * @return McpAvailability the state
     */
    public function state(Request $request): McpAvailability;
}

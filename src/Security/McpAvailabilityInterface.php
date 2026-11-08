<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

use Symfony\Component\HttpFoundation\Request;

/**
 * The host's switches: an instance setting that turns MCP off, maintenance.
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

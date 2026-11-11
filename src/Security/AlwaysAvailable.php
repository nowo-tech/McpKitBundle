<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

use Nowo\McpKitBundle\Http\McpHttpRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * Default availability: always available.
 */
final readonly class AlwaysAvailable implements McpRequestAvailabilityInterface, McpAvailabilityInterface
{
    /**
     * Always available.
     *
     * @param McpHttpRequest $request the MCP request
     *
     * @return McpAvailability {@see McpAvailability::Available}
     */
    public function availabilityOf(McpHttpRequest $request): McpAvailability
    {
        return McpAvailability::Available;
    }

    /**
     * Always available.
     *
     * @param Request $request the MCP request
     *
     * @return McpAvailability {@see McpAvailability::Available}
     *
     * @deprecated since 1.1.0, use {@see availabilityOf()}; removed in 2.0.0
     */
    public function state(Request $request): McpAvailability
    {
        return McpAvailability::Available;
    }
}

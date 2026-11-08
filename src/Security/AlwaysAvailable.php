<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

use Symfony\Component\HttpFoundation\Request;

/**
 * Default availability: always available.
 */
final readonly class AlwaysAvailable implements McpAvailabilityInterface
{
    /**
     * Always available.
     *
     * @param Request $request the MCP request
     *
     * @return McpAvailability {@see McpAvailability::Available}
     */
    public function state(Request $request): McpAvailability
    {
        return McpAvailability::Available;
    }
}

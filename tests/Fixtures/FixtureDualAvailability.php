<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures;

use Nowo\McpKitBundle\Http\McpHttpRequest;
use Nowo\McpKitBundle\Security\McpAvailability;
use Nowo\McpKitBundle\Security\McpAvailabilityInterface;
use Nowo\McpKitBundle\Security\McpRequestAvailabilityInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Implements both availability ports: the deprecated method always disables the endpoint, so a test sees which one
 * the bundle calls.
 */
final class FixtureDualAvailability implements McpAvailabilityInterface, McpRequestAvailabilityInterface
{
    public McpAvailability $state = McpAvailability::Available;

    public function state(Request $request): McpAvailability
    {
        return $request->getMethod() === 'NEVER' ? $this->state : McpAvailability::Disabled;
    }

    public function availabilityOf(McpHttpRequest $request): McpAvailability
    {
        return $this->state;
    }
}

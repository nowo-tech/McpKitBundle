<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures;

use Nowo\McpKitBundle\Security\McpAvailability;
use Nowo\McpKitBundle\Security\McpAvailabilityInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test availability switch.
 */
final class FixtureAvailability implements McpAvailabilityInterface
{
    public McpAvailability $state = McpAvailability::Available;

    public function state(Request $request): McpAvailability
    {
        return $this->state;
    }
}

<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures;

use Nowo\McpKitBundle\Http\McpHttpRequest;
use Nowo\McpKitBundle\Security\McpAvailability;
use Nowo\McpKitBundle\Security\McpRequestAvailabilityInterface;

/**
 * Test availability switch on the framework-free port (DDD host).
 */
final class FixtureRequestAvailability implements McpRequestAvailabilityInterface
{
    public McpAvailability $state = McpAvailability::Available;

    public function availabilityOf(McpHttpRequest $request): McpAvailability
    {
        return $this->state;
    }
}

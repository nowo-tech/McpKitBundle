<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Orm;

use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Security\McpPrincipalRefresherInterface;
use stdClass;

/**
 * A host refresher (hook) that reloads the user and rebuilds the host context.
 */
final class HostContextRefresher implements McpPrincipalRefresherInterface
{
    public function __construct(
        private readonly OrmUserProvider $users,
    ) {
    }

    public function refresh(McpPrincipal $principal): McpPrincipal
    {
        $context            = new stdClass();
        $context->refreshed = true;

        return new McpPrincipal($this->users->refreshUser($principal->user instanceof OrmUser ? $principal->user : new OrmUser('grace')), $principal->tokenId, $principal->tokenName, $principal->destructiveAllowed, $principal->grants, $context);
    }
}

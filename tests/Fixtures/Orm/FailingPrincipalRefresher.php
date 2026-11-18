<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Orm;

use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Security\McpPrincipalRefresherInterface;
use RuntimeException;

/**
 * A host refresher that fails: the event must still be dispatched, with the original principal.
 */
final class FailingPrincipalRefresher implements McpPrincipalRefresherInterface
{
    public function refresh(McpPrincipal $principal): McpPrincipal
    {
        throw new RuntimeException('user store down');
    }
}

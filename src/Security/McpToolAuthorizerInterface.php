<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

use Nowo\McpKitBundle\Tool\McpToolDefinition;

/**
 * The host's per-tool decision: which tools a principal may list and call. Decide on the token's levels and ticks only;
 * where, the plan and the person's permissions are checked by the tool on each call. Destructive tools are already
 * hidden by the bundle when the principal lacks the destructive tick.
 */
interface McpToolAuthorizerInterface
{
    /**
     * Whether the principal may list and call the tool.
     *
     * @param McpPrincipal $principal the principal
     * @param McpToolDefinition $definition the tool
     *
     * @return bool true when allowed
     */
    public function allows(McpPrincipal $principal, McpToolDefinition $definition): bool;
}

<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

use Nowo\McpKitBundle\Tool\McpResourceTemplateDefinition;
use Nowo\McpKitBundle\Tool\McpToolDefinition;

/**
 * Default authorizer: allows nothing, so a host lists no tool until it decides.
 */
final readonly class DenyingMcpToolAuthorizer implements McpToolAuthorizerInterface
{
    /**
     * Refuses.
     *
     * @param McpPrincipal $principal the principal
     * @param McpResourceTemplateDefinition|McpToolDefinition $definition the tool
     *
     * @return bool false
     */
    public function allows(McpPrincipal $principal, McpToolDefinition|McpResourceTemplateDefinition $definition): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace App\Mcp;

use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Security\McpToolAuthorizerInterface;
use Nowo\McpKitBundle\Tool\McpResourceTemplateDefinition;
use Nowo\McpKitBundle\Tool\McpToolDefinition;

/**
 * Level check only: `notes:read` needs the read or edit level, `notes:edit` needs edit.
 */
final readonly class DemoAuthorizer implements McpToolAuthorizerInterface
{
    public function allows(McpPrincipal $principal, McpToolDefinition|McpResourceTemplateDefinition $definition): bool
    {
        $level = $principal->grants['notes'] ?? null;
        foreach ($definition->requires as $grant) {
            $ok = match ($grant) {
                'notes:read' => $level === 'read' || $level === 'edit',
                'notes:edit' => $level === 'edit',
                'conformance' => ($principal->grants['conformance'] ?? null) === 'yes',
                default      => false,
            };
            if (!$ok) {
                return false;
            }
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures;

use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Security\McpToolAuthorizerInterface;
use Nowo\McpKitBundle\Tool\McpToolDefinition;

use function in_array;

/**
 * Test authorizer: `demo:read` needs the `read` or `full` level, `demo:write` needs `full`.
 */
final class FixtureAuthorizer implements McpToolAuthorizerInterface
{
    public bool $allowEverything = false;

    public function allows(McpPrincipal $principal, McpToolDefinition $definition): bool
    {
        if ($this->allowEverything) {
            return true;
        }
        $level = $principal->grants['demo'] ?? null;
        foreach ($definition->requires as $grant) {
            $ok = match ($grant) {
                'demo:read'  => in_array($level, ['read', 'full'], true),
                'demo:write' => $level === 'full',
                default      => false,
            };
            if (!$ok) {
                return false;
            }
        }

        return true;
    }
}

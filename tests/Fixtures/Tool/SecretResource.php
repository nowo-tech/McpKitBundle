<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Tool;

use Nowo\McpKitBundle\Tool\McpResourceRead;
use Nowo\McpKitBundle\Tool\McpResourceTemplateDefinition;
use Nowo\McpKitBundle\Tool\McpResourceTemplateInterface;
use Nowo\McpKitBundle\Tool\McpToolResult;

/**
 * Fixture template only `full` tokens may read.
 */
final class SecretResource implements McpResourceTemplateInterface
{
    public static function definition(): McpResourceTemplateDefinition
    {
        return new McpResourceTemplateDefinition(
            'secret',
            'demo://secret/{key}/{n}',
            'Secret',
            'Restricted.',
            ['key' => '[a-z]+', 'n' => '[0-9]{1,3}'],
            ['type' => 'object', 'additionalProperties' => false, 'properties' => ['n' => ['type' => 'integer']]],
            ['demo:write'],
        );
    }

    public function read(McpResourceRead $read): McpToolResult
    {
        return McpToolResult::ok(['n' => (int) $read->variables['n']]);
    }
}

<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\McpKitBundle\Tool\McpResourceRead;
use Nowo\McpKitBundle\Tool\McpResourceTemplateDefinition;
use Nowo\McpKitBundle\Tool\McpResourceTemplateInterface;
use Nowo\McpKitBundle\Tool\McpToolResult;

/**
 * A template whose placeholder has no regular expression.
 */
final class InvalidTemplate implements McpResourceTemplateInterface
{
    public static function definition(): McpResourceTemplateDefinition
    {
        return new McpResourceTemplateDefinition('bad_tpl', 'demo://x/{id}', 'Bad', 'Bad.', [], ['type' => 'object', 'additionalProperties' => false]);
    }

    public function read(McpResourceRead $read): McpToolResult
    {
        return McpToolResult::ok([]);
    }
}

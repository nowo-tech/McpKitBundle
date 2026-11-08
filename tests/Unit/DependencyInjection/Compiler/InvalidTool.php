<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolInterface;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Nowo\McpKitBundle\Tool\McpToolResult;

/**
 * A tool whose output schema puts free text outside `untrusted`.
 */
final class InvalidTool implements McpToolInterface
{
    public static function definition(): McpToolDefinition
    {
        return new McpToolDefinition('bad_tool', 'Bad', 'Bad.', McpToolKind::ReadOnly, true, ['type' => 'object', 'additionalProperties' => false], ['type' => 'object', 'additionalProperties' => false, 'properties' => ['title' => ['type' => 'string']]]);
    }

    public function call(McpToolCall $call): McpToolResult
    {
        return McpToolResult::ok([]);
    }
}

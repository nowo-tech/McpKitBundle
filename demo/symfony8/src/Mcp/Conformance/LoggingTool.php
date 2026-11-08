<?php

declare(strict_types=1);

namespace App\Mcp\Conformance;

use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolInterface;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Nowo\McpKitBundle\Tool\McpToolResult;

/**
 * Diagnostic fixture of the official conformance runner (`server-stateless`).
 */
final readonly class LoggingTool implements McpToolInterface
{
    public static function definition(): McpToolDefinition
    {
        return new McpToolDefinition(
            'test_logging_tool',
            'Conformance logging',
            'Conformance fixture: returns a result and never emits log notifications (this server has no logging).',
            McpToolKind::ReadOnly,
            true,
            ['type' => 'object', 'additionalProperties' => false],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['done'], 'properties' => ['done' => ['const' => true]]],
            ['conformance'],
            false,
            -10,
        );
    }

    public function call(McpToolCall $call): McpToolResult
    {
        return McpToolResult::ok(['done' => true]);
    }
}

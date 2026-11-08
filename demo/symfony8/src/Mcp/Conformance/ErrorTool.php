<?php

declare(strict_types=1);

namespace App\Mcp\Conformance;

use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolError;
use Nowo\McpKitBundle\Tool\McpToolInterface;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Nowo\McpKitBundle\Tool\McpToolResult;

/**
 * Fixture of the official conformance runner (`tools-call-error`): always a tool error.
 */
final readonly class ErrorTool implements McpToolInterface
{
    public static function definition(): McpToolDefinition
    {
        return new McpToolDefinition(
            'test_error_handling',
            'Conformance: error',
            'Conformance fixture: always returns a tool error.',
            McpToolKind::ReadOnly,
            true,
            ['type' => 'object', 'additionalProperties' => false],
            ['type' => 'object', 'additionalProperties' => false],
            ['conformance'],
            false,
            -10,
        );
    }

    public function call(McpToolCall $call): McpToolResult
    {
        throw new McpToolError('TEST_001', 'This tool intentionally returns an error for testing');
    }
}

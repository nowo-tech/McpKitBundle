<?php

declare(strict_types=1);

namespace App\Mcp\Conformance;

use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolInterface;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Nowo\McpKitBundle\Tool\McpToolResult;

/**
 * Fixture of the official conformance runner (`tools-call-simple-text`); listed only for the conformance token.
 */
final readonly class SimpleTextTool implements McpToolInterface
{
    public static function definition(): McpToolDefinition
    {
        return new McpToolDefinition(
            'test_simple_text',
            'Conformance: simple text',
            'Conformance fixture: returns a fixed text.',
            McpToolKind::ReadOnly,
            true,
            ['type' => 'object', 'additionalProperties' => false],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['message'], 'properties' => [
                'message' => ['const' => 'This is a simple text response for testing.'],
            ]],
            ['conformance'],
            false,
            -10,
        );
    }

    public function call(McpToolCall $call): McpToolResult
    {
        return McpToolResult::ok(['message' => 'This is a simple text response for testing.']);
    }
}

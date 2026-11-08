<?php

declare(strict_types=1);

namespace App\Mcp\Conformance;

use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolInterface;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Nowo\McpKitBundle\Tool\McpToolResult;

/**
 * E2E fixture (conformance profile only): an additive tool that "saves" and then returns a result that breaks its own
 * output schema, so the kit answers SAVED_001. The TypeScript SDK must hand that answer to the model (audit IH-L2r).
 */
final readonly class SavedWithoutResultTool implements McpToolInterface
{
    public static function definition(): McpToolDefinition
    {
        return new McpToolDefinition(
            'test_saved_without_result',
            'E2E saved without result',
            'E2E fixture: saves, then returns a result that cannot be shown.',
            McpToolKind::Additive,
            false,
            ['type' => 'object', 'additionalProperties' => false],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['id'], 'properties' => ['id' => ['type' => 'string', 'format' => 'uuid']]],
            ['conformance'],
            false,
            -10,
        );
    }

    public function call(McpToolCall $call): McpToolResult
    {
        return McpToolResult::ok(['id' => 'not-a-uuid'], ['note' => '0b8f3c5e-6a1d-4c7e-9f2a-1b3c5d7e9f01'], ['title']);
    }
}

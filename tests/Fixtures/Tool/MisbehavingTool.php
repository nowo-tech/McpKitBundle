<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Tool;

use DateTimeImmutable;
use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolInterface;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Nowo\McpKitBundle\Tool\McpToolResult;
use Nowo\McpKitBundle\Tool\McpUntrusted;
use RuntimeException;
use stdClass;

use function str_repeat;

/**
 * Read-only fixture tool that breaks its contract on demand.
 */
final class MisbehavingTool implements McpToolInterface
{
    public static function definition(): McpToolDefinition
    {
        return new McpToolDefinition(
            'misbehave',
            'Misbehave',
            'Breaks its contract.',
            McpToolKind::ReadOnly,
            true,
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['mode'], 'properties' => ['mode' => ['enum' => ['schema', 'throw', 'big', 'object', 'misplaced', 'empty']]]],
            ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                'count'     => ['type' => 'integer'],
                'untrusted' => ['type' => 'object'],
                'items'     => ['type' => 'object', 'additionalProperties' => false],
            ]],
            ['demo:read'],
        );
    }

    public function call(McpToolCall $call): McpToolResult
    {
        return match ($call->arguments['mode']) {
            'schema'    => McpToolResult::ok(['count' => 'many']),
            'throw'     => throw new RuntimeException('secret bt_tool_leak'),
            'big'       => McpToolResult::ok(['untrusted' => McpUntrusted::of(['text' => str_repeat('x', 5000)])]),
            'object'    => McpToolResult::ok(['count' => new DateTimeImmutable()]),
            'misplaced' => McpToolResult::ok(['untrusted' => 'plain']),
            default     => McpToolResult::ok(['items' => new stdClass(), 'untrusted' => McpUntrusted::of([])]),
        };
    }
}

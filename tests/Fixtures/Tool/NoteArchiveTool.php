<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Tool;

use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolInterface;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Nowo\McpKitBundle\Tool\McpToolResult;

/**
 * Destructive fixture tool.
 */
final class NoteArchiveTool implements McpToolInterface
{
    public static function definition(): McpToolDefinition
    {
        return new McpToolDefinition(
            'note_archive',
            'Archive a note',
            'Archives one note.',
            McpToolKind::Destructive,
            true,
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['id'], 'properties' => ['id' => ['type' => 'string', 'format' => 'uuid', 'maxLength' => 36]]],
            ['type' => 'object', 'additionalProperties' => false, 'properties' => ['archived' => ['type' => 'boolean']]],
            ['demo:write'],
        );
    }

    public function call(McpToolCall $call): McpToolResult
    {
        return McpToolResult::ok(['archived' => true], ['note' => (string) $call->arguments['id']], ['archived']);
    }
}

<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use App\Mcp\NoteStore;
use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolError;
use Nowo\McpKitBundle\Tool\McpToolInterface;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Nowo\McpKitBundle\Tool\McpToolResult;

/**
 * Archive one note (destructive: listed only for tokens with the destructive tick).
 */
final readonly class NoteArchiveTool implements McpToolInterface
{
    public function __construct(
        private NoteStore $notes,
    ) {
    }

    public static function definition(): McpToolDefinition
    {
        return new McpToolDefinition(
            'note_archive',
            'Archive a note',
            'Archives one note by id. It disappears from reads.',
            McpToolKind::Destructive,
            true,
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['id'], 'properties' => [
                'id' => ['type' => 'string', 'format' => 'uuid', 'maxLength' => 36],
            ]],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['id', 'archived'], 'properties' => [
                'id'       => ['type' => 'string', 'format' => 'uuid'],
                'archived' => ['type' => 'boolean'],
            ]],
            ['notes:edit'],
        );
    }

    public function call(McpToolCall $call): McpToolResult
    {
        $note = $this->notes->get((string) $call->arguments['id']);
        if ($note === null || $note['archived']) {
            throw McpToolError::notFound();
        }
        $note['archived'] = true;
        $this->notes->save($note);

        return McpToolResult::ok(['id' => $note['id'], 'archived' => true], ['note' => $note['id']], ['archived']);
    }
}

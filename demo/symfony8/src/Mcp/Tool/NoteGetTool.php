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
use Nowo\McpKitBundle\Tool\McpUntrusted;

/**
 * Read one note. Title and body are written by people: they go under `untrusted`.
 */
final readonly class NoteGetTool implements McpToolInterface
{
    public function __construct(
        private NoteStore $notes,
    ) {
    }

    public static function definition(): McpToolDefinition
    {
        return new McpToolDefinition(
            'note_get',
            'Get a note',
            'Returns one note by id: title and Markdown body.',
            McpToolKind::ReadOnly,
            true,
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['id'], 'properties' => [
                'id' => ['type' => 'string', 'format' => 'uuid', 'maxLength' => 36, 'description' => 'Note id (UUID).'],
            ]],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['id', 'created_at', 'untrusted'], 'properties' => [
                'id'         => ['type' => 'string', 'format' => 'uuid'],
                'created_at' => ['type' => 'string', 'format' => 'date-time'],
                'untrusted'  => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'body' => ['type' => 'string']]],
            ]],
            ['notes:read'],
            false,
            20,
        );
    }

    public function call(McpToolCall $call): McpToolResult
    {
        $note = $this->notes->get((string) $call->arguments['id']);
        if ($note === null || $note['archived']) {
            throw McpToolError::notFound();
        }

        return McpToolResult::ok(
            ['id' => $note['id'], 'created_at' => $note['created_at'], 'untrusted' => McpUntrusted::of(['title' => $note['title'], 'body' => $note['body']])],
            ['note' => $note['id']],
        );
    }
}

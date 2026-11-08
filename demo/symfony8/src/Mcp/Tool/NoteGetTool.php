<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use App\Mcp\NoteStore;
use Nowo\McpKitBundle\Paging\McpLimits;
use Nowo\McpKitBundle\Paging\McpTextSlice;
use Nowo\McpKitBundle\Tool\McpResourceLink;
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
        private McpLimits $limits,
    ) {
    }

    public static function definition(): McpToolDefinition
    {
        return new McpToolDefinition(
            'note_get',
            'Get a note',
            'Returns one note by id: title and Markdown body. Long bodies come in pages: when truncated is true, call again with offset = next_offset.',
            McpToolKind::ReadOnly,
            true,
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['id'], 'properties' => [
                'id'     => ['type' => 'string', 'format' => 'uuid', 'maxLength' => 36, 'description' => 'Note id (UUID).'],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Character offset in the body (next_offset of the previous answer).'],
            ]],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['id', 'created_at', 'truncated', 'next_offset', 'total', 'untrusted'], 'properties' => [
                'id'          => ['type' => 'string', 'format' => 'uuid'],
                'created_at'  => ['type' => 'string', 'format' => 'date-time'],
                'truncated'   => ['type' => 'boolean'],
                'next_offset' => ['type' => ['integer', 'null']],
                'total'       => ['type' => 'integer'],
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

        $slice = McpTextSlice::of($note['body'], (int) ($call->arguments['offset'] ?? 0), $this->limits->maxTextChars);

        return McpToolResult::ok(
            ['id' => $note['id'], 'created_at' => $note['created_at'], ...$slice->paging(), 'untrusted' => McpUntrusted::of(['title' => $note['title'], 'body' => $slice->text])],
            ['note' => $note['id']],
            [],
            [new McpResourceLink('demo://note/' . $note['id'], 'note')],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use App\Mcp\NoteStore;
use Nowo\McpKitBundle\Paging\McpCursor;
use Nowo\McpKitBundle\Paging\McpLimits;
use Nowo\McpKitBundle\Tool\McpResourceLink;
use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolInterface;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Nowo\McpKitBundle\Tool\McpToolResult;
use Nowo\McpKitBundle\Tool\McpUntrusted;

/**
 * List notes, a page at a time (rows capped by limits.max_list_rows, opaque cursor).
 */
final readonly class NoteListTool implements McpToolInterface
{
    public function __construct(
        private NoteStore $notes,
        private McpLimits $limits,
    ) {
    }

    public static function definition(): McpToolDefinition
    {
        return new McpToolDefinition(
            'note_list',
            'List notes',
            'Lists the notes that are not archived, newest first. Pass next_cursor back as cursor for the next page.',
            McpToolKind::ReadOnly,
            true,
            ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                'cursor' => McpCursor::schema(),
            ]],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['notes', 'next_cursor'], 'properties' => [
                'notes' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['id', 'untrusted'], 'properties' => [
                    'id'        => ['type' => 'string', 'format' => 'uuid'],
                    'untrusted' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string']]],
                ]]],
                'next_cursor' => ['type' => ['string', 'null'], 'pattern' => '^[A-Za-z0-9_-]+$', 'maxLength' => 512],
            ]],
            ['notes:read'],
            false,
            30,
        );
    }

    public function call(McpToolCall $call): McpToolResult
    {
        $offset = (int) (McpCursor::decode(isset($call->arguments['cursor']) ? (string) $call->arguments['cursor'] : null)['o'] ?? 0);
        $notes  = array_values(array_filter($this->notes->all(), static fn (array $note): bool => !$note['archived']));
        usort($notes, static fn (array $a, array $b): int => $b['created_at'] <=> $a['created_at']);
        $page   = array_slice($notes, max(0, $offset), $this->limits->maxListRows);
        $next   = $offset + count($page) < count($notes) ? McpCursor::encode(['o' => $offset + count($page)]) : null;

        return McpToolResult::ok(
            [
                'notes'       => array_map(static fn (array $note): array => ['id' => $note['id'], 'untrusted' => McpUntrusted::of(['title' => $note['title']])], $page),
                'next_cursor' => $next,
            ],
            [],
            [],
            array_map(static fn (array $note): McpResourceLink => new McpResourceLink('demo://note/' . $note['id'], 'note'), $page),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Mcp;

use Nowo\McpKitBundle\Tool\McpResourceRead;
use Nowo\McpKitBundle\Tool\McpResourceTemplateDefinition;
use Nowo\McpKitBundle\Tool\McpResourceTemplateInterface;
use Nowo\McpKitBundle\Tool\McpToolError;
use Nowo\McpKitBundle\Tool\McpToolResult;
use Nowo\McpKitBundle\Tool\McpUntrusted;

/**
 * `demo://note/{id}`: one note as JSON, with the same access and envelope as note_get.
 */
final readonly class NoteResource implements McpResourceTemplateInterface
{
    public function __construct(
        private NoteStore $notes,
    ) {
    }

    public static function definition(): McpResourceTemplateDefinition
    {
        return new McpResourceTemplateDefinition(
            'note',
            'demo://note/{id}',
            'Note',
            'One note: title and Markdown body.',
            ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['id', 'untrusted'], 'properties' => [
                'id'        => ['type' => 'string', 'format' => 'uuid'],
                'untrusted' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'body' => ['type' => 'string']]],
            ]],
            ['notes:read'],
        );
    }

    public function read(McpResourceRead $read): McpToolResult
    {
        $note = $this->notes->get($read->variables['id']);
        if ($note === null || $note['archived']) {
            throw McpToolError::notFound();
        }

        return McpToolResult::ok(['id' => $note['id'], 'untrusted' => McpUntrusted::of(['title' => $note['title'], 'body' => $note['body']])], ['note' => $note['id']]);
    }
}

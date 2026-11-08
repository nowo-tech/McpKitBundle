<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Tool;

use Nowo\McpKitBundle\Tool\McpIdempotencyKey;
use Nowo\McpKitBundle\Tool\McpToolCall;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolError;
use Nowo\McpKitBundle\Tool\McpToolInterface;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Nowo\McpKitBundle\Tool\McpToolResult;

/**
 * Additive fixture tool recording its calls.
 */
final class NoteCreateTool implements McpToolInterface
{
    /** @var list<McpToolCall> */
    public array $calls = [];

    public static function definition(): McpToolDefinition
    {
        return new McpToolDefinition(
            'note_create',
            'Create a note',
            "Creates a note.\nTitles are plain text.",
            McpToolKind::Additive,
            false,
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['title'], 'properties' => [
                'title'                     => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
                'tags'                      => ['type' => 'array', 'maxItems' => 3, 'items' => ['type' => 'string', 'maxLength' => 20]],
                'meta'                      => ['type' => 'object', 'additionalProperties' => false, 'properties' => ['pinned' => ['type' => 'boolean']]],
                McpIdempotencyKey::ARGUMENT => McpIdempotencyKey::schema(),
            ]],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['id'], 'properties' => [
                'id'      => ['type' => 'string', 'format' => 'uuid'],
                'status'  => ['enum' => ['created']],
                'version' => ['type' => 'integer'],
            ]],
            ['demo:write'],
        );
    }

    public function call(McpToolCall $call): McpToolResult
    {
        $this->calls[] = $call;
        if ($call->arguments['title'] === 'conflict') {
            throw new McpToolError('WIKI_409', 'Stale revision', ['current_revision' => 13, 'ECODE' => 'IGNORED']);
        }

        return McpToolResult::ok(['id' => '5d1f0c2a-3b4c-4d5e-8f60-718293a4b5c6', 'status' => 'created', 'version' => 1], ['note' => '5d1f0c2a-3b4c-4d5e-8f60-718293a4b5c6'], ['title']);
    }
}

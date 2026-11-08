<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Tool;

use Nowo\McpKitBundle\Tool\McpResourceRead;
use Nowo\McpKitBundle\Tool\McpResourceTemplateDefinition;
use Nowo\McpKitBundle\Tool\McpResourceTemplateInterface;
use Nowo\McpKitBundle\Tool\McpToolError;
use Nowo\McpKitBundle\Tool\McpToolResult;
use Nowo\McpKitBundle\Tool\McpUntrusted;
use RuntimeException;

use function str_repeat;

/**
 * Fixture template `demo://note/{id}`; special ids misbehave.
 */
final class NoteResource implements McpResourceTemplateInterface
{
    /** @var list<McpResourceRead> */
    public array $reads = [];

    public static function definition(): McpResourceTemplateDefinition
    {
        return new McpResourceTemplateDefinition(
            'note',
            'demo://note/{id}',
            'Note',
            'One note as JSON.',
            ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['id'], 'properties' => [
                'id'        => ['type' => 'string', 'format' => 'uuid'],
                'untrusted' => ['type' => 'object'],
            ]],
            ['demo:read'],
            5,
        );
    }

    public function read(McpResourceRead $read): McpToolResult
    {
        $this->reads[] = $read;

        return match ($read->variables['id']) {
            NoteGetTool::ID                        => McpToolResult::ok(['id' => NoteGetTool::ID, 'untrusted' => McpUntrusted::of(['title' => 'Runbook', 'body' => NoteGetTool::INJECTION])], ['note' => NoteGetTool::ID]),
            '00000000-0000-4000-8000-000000000001' => McpToolResult::ok(['id' => '00000000-0000-4000-8000-000000000001']),
            '00000000-0000-4000-8000-000000000002' => throw new RuntimeException('secret bt_template_leak'),
            '00000000-0000-4000-8000-000000000003' => McpToolResult::ok(['id' => 'not-a-uuid']),
            '00000000-0000-4000-8000-000000000004' => McpToolResult::ok(['id' => '00000000-0000-4000-8000-000000000004', 'untrusted' => McpUntrusted::of(['body' => str_repeat('y', 5000)])]),
            default                                => throw McpToolError::notFound(),
        };
    }
}

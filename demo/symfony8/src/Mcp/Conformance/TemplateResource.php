<?php

declare(strict_types=1);

namespace App\Mcp\Conformance;

use Nowo\McpKitBundle\Tool\McpResourceRead;
use Nowo\McpKitBundle\Tool\McpResourceTemplateDefinition;
use Nowo\McpKitBundle\Tool\McpResourceTemplateInterface;
use Nowo\McpKitBundle\Tool\McpToolResult;

/**
 * Fixture of the official conformance runner (`resources-templates-read`): `test://template/{id}/data`.
 */
final readonly class TemplateResource implements McpResourceTemplateInterface
{
    public static function definition(): McpResourceTemplateDefinition
    {
        return new McpResourceTemplateDefinition(
            'test_template',
            'test://template/{id}/data',
            'Conformance: template',
            'Conformance fixture.',
            ['id' => '[0-9]{1,9}'],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['id', 'templateTest'], 'properties' => [
                'id'           => ['type' => 'string', 'pattern' => '^[0-9]{1,9}$', 'maxLength' => 9],
                'templateTest' => ['const' => true],
            ]],
            ['conformance'],
        );
    }

    public function read(McpResourceRead $read): McpToolResult
    {
        return McpToolResult::ok(['id' => $read->variables['id'], 'templateTest' => true]);
    }
}

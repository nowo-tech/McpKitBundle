<?php

declare(strict_types=1);

namespace App\Mcp\Conformance;

use Nowo\McpKitBundle\Tool\McpResourceRead;
use Nowo\McpKitBundle\Tool\McpResourceTemplateDefinition;
use Nowo\McpKitBundle\Tool\McpResourceTemplateInterface;
use Nowo\McpKitBundle\Tool\McpToolResult;

/**
 * Fixture of the official conformance runner (`resources-read-text`): `test://static-text`, a template without variables.
 */
final readonly class StaticTextResource implements McpResourceTemplateInterface
{
    public static function definition(): McpResourceTemplateDefinition
    {
        return new McpResourceTemplateDefinition(
            'test_static_text',
            'test://static-text',
            'Conformance: static text',
            'Conformance fixture.',
            [],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['text'], 'properties' => [
                'text' => ['const' => 'This is the content of the static text resource.'],
            ]],
            ['conformance'],
        );
    }

    public function read(McpResourceRead $read): McpToolResult
    {
        return McpToolResult::ok(['text' => 'This is the content of the static text resource.']);
    }
}

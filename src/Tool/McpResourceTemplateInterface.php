<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tool;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A resource template the host offers (`resources/templates/list`, `resources/read`). Autoconfigured with the
 * `nowo_mcp_kit.resource_template` tag.
 */
#[AutoconfigureTag('nowo_mcp_kit.resource_template')]
interface McpResourceTemplateInterface
{
    /**
     * The definition, static and read once when the container is built.
     *
     * @return McpResourceTemplateDefinition the definition
     */
    public static function definition(): McpResourceTemplateDefinition;

    /**
     * Reads one resource as the principal. Throw {@see McpToolError} for anything missing or out of reach: the client
     * gets the same `-32602` "Resource not found" for every reason.
     *
     * @param McpResourceRead $read the read
     *
     * @return McpToolResult the contents' data
     */
    public function read(McpResourceRead $read): McpToolResult;
}

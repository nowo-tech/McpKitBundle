<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tool;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A tool the host offers to agents. Autoconfigured with the `nowo_mcp_kit.tool` tag.
 */
#[AutoconfigureTag('nowo_mcp_kit.tool')]
interface McpToolInterface
{
    /**
     * The definition. Static on purpose: read once when the container is built, from code, with no service, request or
     * user data.
     *
     * @return McpToolDefinition the definition
     */
    public static function definition(): McpToolDefinition;

    /**
     * Runs the tool as the principal. Arguments are already validated against the input schema.
     *
     * @param McpToolCall $call the call
     *
     * @throws McpToolError for business refusals (answered as `isError: true`)
     *
     * @return McpToolResult the result
     */
    public function call(McpToolCall $call): McpToolResult;
}

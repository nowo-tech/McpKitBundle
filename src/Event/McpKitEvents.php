<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Event;

/**
 * Event names of the bundle (the event classes; Symfony listeners can also type-hint the class).
 */
final class McpKitEvents
{
    /** {@see McpToolCallEvent}: after every tool call or resource read that reaches the registry. */
    public const TOOL_CALL = McpToolCallEvent::class;
}

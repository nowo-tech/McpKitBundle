<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Event;

/**
 * How a tool call or resource read ended.
 */
enum McpCallOutcome: string
{
    case Ok = 'ok';
    /** A write was committed but its result could not be returned (output schema, envelope, encoding, size). */
    case SavedWithoutResult = 'saved_without_result';
    /** A business refusal of the tool (`McpToolError`), or `AUDIT_001` / `OUTPUT_001`. */
    case ToolError = 'tool_error';
    /** The token may not use the tool (`TOKEN_001`). */
    case Refused = 'refused';
    /** Arguments did not match the input schema (`INPUT_001`, `INPUT_002`). */
    case InvalidInput = 'invalid_input';
    /** The write budget refused the call (429). */
    case RateLimited = 'rate_limited';
    /** An unexpected error (`-32603`). */
    case InternalError = 'internal_error';
}

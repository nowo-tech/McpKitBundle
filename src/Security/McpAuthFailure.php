<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

/**
 * Why the host's authenticator gave no principal. One answer per case, never a reason in the body.
 */
enum McpAuthFailure
{
    /** Unknown, revoked, expired, disabled, erased or removed: 401, counted as a failed token. */
    case Invalid;
    /** MCP is switched off for this token (its organization, the API switch): 404. */
    case Disabled;
}

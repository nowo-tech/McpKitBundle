<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

/**
 * Whether the endpoint serves requests right now.
 */
enum McpAvailability
{
    case Available;
    /** Switched off (instance setting): 404 with no body, as if the endpoint did not exist. */
    case Disabled;
    /** Maintenance: 503 with `Retry-After`. */
    case Maintenance;
}

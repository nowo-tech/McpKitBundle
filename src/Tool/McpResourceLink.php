<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tool;

/**
 * A `resource_link` content block of a tool result. The name is static text from code.
 */
final readonly class McpResourceLink
{
    /**
     * Creates the link.
     *
     * @param string $uri the resource URI (one of the host's templates)
     * @param string $name static name, e.g. `task`
     */
    public function __construct(
        public string $uri,
        public string $name,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tool;

use InvalidArgumentException;

use function preg_match;
use function strlen;

/**
 * A `resource_link` content block of a tool result: a static snake_case name and a URI of visible ASCII characters.
 */
final readonly class McpResourceLink
{
    /**
     * Creates the link.
     *
     * @param string $uri the resource URI (one of the host's templates)
     * @param string $name static name, e.g. `task`
     *
     * @throws InvalidArgumentException on an invalid name or URI
     */
    public function __construct(
        public string $uri,
        public string $name,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name) !== 1 || strlen($uri) > 2048 || preg_match('/^[a-z][a-z0-9+.-]*:\/\/[\x21-\x7E]+$/D', $uri) !== 1) {
            throw new InvalidArgumentException('McpResourceLink needs a snake_case static name and a URI of visible ASCII characters (at most 2,048).');
        }
    }
}

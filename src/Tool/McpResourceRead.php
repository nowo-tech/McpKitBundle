<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tool;

use Nowo\McpKitBundle\Context\McpCallContext;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Symfony\Component\HttpFoundation\Request;

/**
 * One `resources/read`: who, which URI, the matched variables.
 */
final readonly class McpResourceRead
{
    /**
     * Creates the read.
     *
     * @param McpPrincipal $principal the authenticated principal
     * @param string $uri the requested URI
     * @param array<string, string> $variables matched template variables
     * @param McpCallContext $context era, version, client and template name
     * @param Request $request the HTTP request (behind the firewall)
     */
    public function __construct(
        public McpPrincipal $principal,
        public string $uri,
        public array $variables,
        public McpCallContext $context,
        public Request $request,
    ) {
    }
}

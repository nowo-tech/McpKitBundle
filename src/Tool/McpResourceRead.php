<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tool;

use Nowo\McpKitBundle\Context\McpCallContext;
use Nowo\McpKitBundle\Http\McpHttpRequest;
use Nowo\McpKitBundle\Http\McpHttpRequestFactory;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Symfony\Component\HttpFoundation\Request;

/**
 * One `resources/read`: who, which URI, the matched variables.
 */
final readonly class McpResourceRead
{
    /** Framework-free view of the HTTP request (method, path, headers, client IP; credentials redacted when logged). */
    public McpHttpRequest $http;

    /**
     * Creates the read.
     *
     * @param McpPrincipal $principal the authenticated principal
     * @param string $uri the requested URI
     * @param array<string, string> $variables matched template variables
     * @param McpCallContext $context era, version, client and template name
     * @param Request $request the HTTP request (behind the firewall); deprecated, read {@see $http} instead
     * @param McpHttpRequest|null $http framework-free view of the request (built from `$request` when null)
     */
    public function __construct(
        public McpPrincipal $principal,
        public string $uri,
        public array $variables,
        public McpCallContext $context,
        /** @deprecated since 1.1.0, read {@see $http} (no HttpFoundation type); removed in 2.0.0 */
        public Request $request,
        ?McpHttpRequest $http = null,
    ) {
        $this->http = $http ?? McpHttpRequestFactory::fromRequest($request);
    }
}

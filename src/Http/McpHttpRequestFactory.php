<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Http;

use Symfony\Component\HttpFoundation\Request;

use function is_string;

/**
 * Builds the framework-free {@see McpHttpRequest} from the HttpFoundation request. This is the only place where the
 * bundle translates the Symfony request for the host-facing ports; hosts never need it.
 *
 * @internal
 */
final class McpHttpRequestFactory
{
    /**
     * Builds the view of a request.
     *
     * @param Request $request the HTTP request
     *
     * @return McpHttpRequest the framework-free view
     */
    public static function fromRequest(Request $request): McpHttpRequest
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            foreach ($values as $value) {
                // A null value stays present (as HeaderBag::has() sees it) but empty, like HeaderBag::get() reads it.
                $headers[$name][] = is_string($value) ? $value : '';
            }
        }

        return new McpHttpRequest(
            $request->getMethod(),
            $request->getPathInfo(),
            $headers,
            $request->getClientIp(),
            $request->getScheme(),
            $request->getHost(),
        );
    }
}

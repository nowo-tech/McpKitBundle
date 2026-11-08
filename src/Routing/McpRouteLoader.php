<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Routing;

use Nowo\McpKitBundle\Http\McpEndpointController;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Loads the endpoint route (`type: nowo_mcp_kit`) at the configured path, or nothing when the bundle is disabled. The
 * route accepts every method so the endpoint itself answers 405 to anything but POST.
 */
final class McpRouteLoader extends Loader
{
    public const TYPE       = 'nowo_mcp_kit';
    public const ROUTE_NAME = 'nowo_mcp_kit_endpoint';

    /**
     * Creates the loader.
     *
     * @param bool $enabled whether the endpoint is registered
     * @param string $path the endpoint path
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly string $path,
    ) {
        parent::__construct();
    }

    /**
     * Loads the route.
     *
     * @param mixed $resource ignored
     * @param string|null $type `nowo_mcp_kit`
     *
     * @return RouteCollection the endpoint route, or an empty collection when disabled
     */
    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();
        if ($this->enabled) {
            $routes->add(self::ROUTE_NAME, new Route($this->path, [
                '_controller' => McpEndpointController::class,
                '_stateless'  => true,
            ]));
        }

        return $routes;
    }

    /**
     * Supports the `nowo_mcp_kit` type.
     *
     * @param mixed $resource ignored
     * @param string|null $type the type
     *
     * @return bool true for `nowo_mcp_kit`
     */
    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === self::TYPE;
    }
}

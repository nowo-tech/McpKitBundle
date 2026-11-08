<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Routing;

use Nowo\McpKitBundle\Http\McpEndpointController;
use Nowo\McpKitBundle\Routing\McpRouteLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(McpRouteLoader::class)]
final class McpRouteLoaderTest extends TestCase
{
    #[Test]
    public function loadsOneStatelessRouteForEveryMethod(): void
    {
        $loader = new McpRouteLoader(true, '/agents/mcp');
        self::assertTrue($loader->supports('.', 'nowo_mcp_kit'));
        self::assertFalse($loader->supports('.', 'yaml'));

        $route = $loader->load('.', 'nowo_mcp_kit')->get('nowo_mcp_kit_endpoint');
        self::assertNotNull($route);
        self::assertSame('/agents/mcp', $route->getPath());
        self::assertSame([], $route->getMethods());
        self::assertSame(McpEndpointController::class, $route->getDefault('_controller'));
        self::assertTrue($route->getDefault('_stateless'));
    }

    #[Test]
    public function loadsNothingWhenDisabled(): void
    {
        self::assertCount(0, (new McpRouteLoader(false, '/mcp'))->load('.', 'nowo_mcp_kit'));
    }
}

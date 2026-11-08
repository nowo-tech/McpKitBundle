<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit;

use Nowo\McpKitBundle\DependencyInjection\NowoMcpKitExtension;
use Nowo\McpKitBundle\NowoMcpKitBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;

#[CoversClass(NowoMcpKitBundle::class)]
final class NowoMcpKitBundleTest extends TestCase
{
    #[Test]
    public function exposesExtensionAndPackageRootPath(): void
    {
        $bundle = new NowoMcpKitBundle();

        self::assertInstanceOf(NowoMcpKitExtension::class, $bundle->getContainerExtension());
        self::assertSame(dirname(__DIR__, 2), $bundle->getPath());
    }
}

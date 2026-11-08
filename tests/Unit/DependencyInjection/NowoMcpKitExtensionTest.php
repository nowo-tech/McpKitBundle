<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\DependencyInjection;

use Nowo\McpKitBundle\DependencyInjection\Configuration;
use Nowo\McpKitBundle\DependencyInjection\NowoMcpKitExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(NowoMcpKitExtension::class)]
#[CoversClass(Configuration::class)]
final class NowoMcpKitExtensionTest extends TestCase
{
    #[Test]
    public function defaultsAreDisabled(): void
    {
        $container = new ContainerBuilder();
        (new NowoMcpKitExtension())->load([], $container);

        self::assertFalse($container->getParameter('nowo_mcp_kit.enabled'));
        self::assertSame('/mcp', $container->getParameter('nowo_mcp_kit.path'));
        self::assertSame('nowo_mcp_kit', (new NowoMcpKitExtension())->getAlias());
    }

    #[Test]
    public function rejectsARelativePath(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new NowoMcpKitExtension())->load([['path' => 'mcp']], new ContainerBuilder());
    }
}

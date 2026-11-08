<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\McpKitBundle\DependencyInjection\Compiler\McpRegistryPass;
use Nowo\McpKitBundle\Registry\McpToolRegistry;
use Nowo\McpKitBundle\Tests\Fixtures\FixtureAuthorizer;
use Nowo\McpKitBundle\Tests\Fixtures\Tool\NoteGetTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

#[CoversClass(McpRegistryPass::class)]
#[CoversClass(McpToolRegistry::class)]
final class McpRegistryPassTest extends TestCase
{
    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(McpToolRegistry::class, McpToolRegistry::class)->setArguments([[], null]);

        return $container;
    }

    #[Test]
    public function doesNothingWithoutTheRegistry(): void
    {
        $container = new ContainerBuilder();
        $container->register('tool', NoteGetTool::class)->addTag(McpRegistryPass::TOOL_TAG);
        (new McpRegistryPass())->process($container);

        self::assertFalse($container->hasDefinition(McpToolRegistry::class));
    }

    #[Test]
    public function refusesAServiceThatIsNotATool(): void
    {
        $container = $this->container();
        $container->register('not_a_tool', FixtureAuthorizer::class)->addTag(McpRegistryPass::TOOL_TAG);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');
        (new McpRegistryPass())->process($container);
    }

    #[Test]
    public function refusesADuplicateName(): void
    {
        $container = $this->container();
        $container->register('a', NoteGetTool::class)->addTag(McpRegistryPass::TOOL_TAG);
        $container->register('b', NoteGetTool::class)->addTag(McpRegistryPass::TOOL_TAG);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('used by two services');
        (new McpRegistryPass())->process($container);
    }

    #[Test]
    public function refusesAnInvalidDefinition(): void
    {
        $container = $this->container();
        $container->register('bad', InvalidTool::class)->addTag(McpRegistryPass::TOOL_TAG);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MCP tool "bad_tool" (bad): ');
        (new McpRegistryPass())->process($container);
    }
}

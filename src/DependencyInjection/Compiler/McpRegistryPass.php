<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\McpKitBundle\Registry\McpToolRegistry;
use Nowo\McpKitBundle\Schema\McpSchemaRules;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

use function array_map;
use function sprintf;
use function usort;

/**
 * Reads every `nowo_mcp_kit.tool` service's static definition at build time, checks it against
 * {@see McpSchemaRules}, and compiles the registry. A violation stops the build.
 */
final class McpRegistryPass implements CompilerPassInterface
{
    public const TOOL_TAG = 'nowo_mcp_kit.tool';

    /**
     * Compiles the tool registry.
     *
     * @param ContainerBuilder $container the container
     *
     * @throws InvalidArgumentException on an invalid or duplicate tool
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(McpToolRegistry::class)) {
            return;
        }

        $definitions = [];
        $services    = [];
        foreach ($container->findTaggedServiceIds(self::TOOL_TAG) as $id => $tags) {
            $class = $container->getReflectionClass((string) $container->getDefinition($id)->getClass());
            if (!$class instanceof ReflectionClass || !$class->implementsInterface(McpToolInterface::class)) {
                throw new InvalidArgumentException(sprintf('MCP tool service "%s" must implement %s.', $id, McpToolInterface::class));
            }
            /** @var class-string<McpToolInterface> $className */
            $className  = $class->getName();
            $definition = $className::definition();
            try {
                McpSchemaRules::checkTool($definition);
            } catch (LogicException $exception) {
                throw new InvalidArgumentException(sprintf('MCP tool "%s" (%s): %s', $definition->name, $id, $exception->getMessage()), 0, $exception);
            }
            if (isset($services[$definition->name])) {
                throw new InvalidArgumentException(sprintf('MCP tool name "%s" is used by two services.', $definition->name));
            }
            $definitions[]               = $definition;
            $services[$definition->name] = new Reference($id);
        }

        usort($definitions, static fn (McpToolDefinition $a, McpToolDefinition $b): int => [$b->priority, $a->name] <=> [$a->priority, $b->name]);

        $container->getDefinition(McpToolRegistry::class)
            ->setArgument(0, array_map(McpToolRegistry::compile(...), $definitions))
            ->setArgument(1, ServiceLocatorTagPass::register($container, $services));
    }
}

<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function is_string;

/**
 * `nowo_mcp_kit` configuration tree. Everything is opt-in: with `enabled: false` nothing reachable is registered.
 */
final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_mcp_kit';

    /**
     * Builds the configuration tree.
     *
     * @return TreeBuilder the `nowo_mcp_kit` tree
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('enabled')
                    ->info('Registers the MCP endpoint. False: the bundle registers nothing reachable.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('path')
                    ->info('Path of the MCP endpoint (POST only), outside any locale prefix.')
                    ->defaultValue('/mcp')
                    ->validate()
                        ->ifTrue(static fn (mixed $path): bool => !is_string($path) || preg_match('#^/[A-Za-z0-9._~/-]*$#', $path) !== 1)
                        ->thenInvalid('nowo_mcp_kit.path must be an absolute path such as "/mcp".')
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}

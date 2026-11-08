<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * Loads the `nowo_mcp_kit` configuration and registers the bundle services.
 */
final class NowoMcpKitExtension extends Extension
{
    /**
     * Configuration alias.
     *
     * @return string `nowo_mcp_kit`
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    /**
     * Loads the configuration.
     *
     * @param array<array<string, mixed>> $configs raw configuration blocks
     * @param ContainerBuilder $container container being built
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{enabled: bool, path: string} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('nowo_mcp_kit.enabled', $config['enabled']);
        $container->setParameter('nowo_mcp_kit.path', $config['path']);
    }
}

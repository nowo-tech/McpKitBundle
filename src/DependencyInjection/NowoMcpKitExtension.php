<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\DependencyInjection;

use Nowo\McpKitBundle\Context\McpCallContextProvider;
use Nowo\McpKitBundle\Http\McpEndpointController;
use Nowo\McpKitBundle\Http\McpEndpointOptions;
use Nowo\McpKitBundle\Routing\McpRouteLoader;
use Nowo\McpKitBundle\Security\AlwaysAvailable;
use Nowo\McpKitBundle\Security\DenyingMcpRateLimiter;
use Nowo\McpKitBundle\Security\McpFirewallGuard;
use Nowo\McpKitBundle\Server\McpDispatcher;
use Nowo\McpKitBundle\Server\McpServerInfo;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

use function array_map;
use function array_values;
use function strtolower;

/**
 * Loads the `nowo_mcp_kit` configuration and registers the bundle services. The route loader is always registered (so a
 * host import never breaks); the endpoint and its services only with `enabled: true`.
 */
final class NowoMcpKitExtension extends Extension
{
    public const OPTIONS_ID     = 'nowo_mcp_kit.endpoint_options';
    public const SERVER_INFO_ID = 'nowo_mcp_kit.server_info';

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
        /** @var array{enabled: bool, path: string, server_info: array{name: string, title: string|null, version: string}, allowed_origins: list<string>, legacy: array{enabled: bool, versions: list<string>}, cache: array{discover_ttl_ms: int, tools_list_ttl_ms: int, templates_list_ttl_ms: int, resources_list_ttl_ms: int}, limits: array{max_body_bytes: int, max_json_depth: int, max_text_chars: int, max_list_rows: int, max_result_bytes: int, slow_call_ms: int}, maintenance_retry_after: int, firewall_check: bool, hooks: array{authenticator: string|null, authorizer: string|null, rate_limiter: string|null, availability: string|null}} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('nowo_mcp_kit.enabled', $config['enabled']);
        $container->setParameter('nowo_mcp_kit.path', $config['path']);
        $container->setParameter('nowo_mcp_kit.limits', $config['limits']);

        $container->register(McpRouteLoader::class, McpRouteLoader::class)
            ->setArguments([$config['enabled'], $config['path']])
            ->addTag('routing.loader');

        if (!$config['enabled']) {
            return;
        }
        if ($config['hooks']['authenticator'] === null) {
            throw new InvalidConfigurationException('nowo_mcp_kit.hooks.authenticator is required when nowo_mcp_kit.enabled is true (the bundle stores no tokens).');
        }

        $container->register(self::OPTIONS_ID, McpEndpointOptions::class)->setArguments([
            array_values(array_map(strtolower(...), $config['allowed_origins'])),
            $config['legacy']['enabled'],
            $config['legacy']['versions'],
            $config['limits']['max_body_bytes'],
            $config['limits']['max_json_depth'],
            $config['maintenance_retry_after'],
        ]);
        $container->register(self::SERVER_INFO_ID, McpServerInfo::class)->setArguments([
            $config['server_info']['name'],
            $config['server_info']['title'],
            $config['server_info']['version'],
        ]);

        $container->register(DenyingMcpRateLimiter::class, DenyingMcpRateLimiter::class);
        $container->register(AlwaysAvailable::class, AlwaysAvailable::class);
        $container->register('nowo_mcp_kit.clock', NativeClock::class);

        $container->register(McpFirewallGuard::class, McpFirewallGuard::class)->setArguments([
            $config['firewall_check'],
            new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('security.helper', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);

        $container->register(McpDispatcher::class, McpDispatcher::class)->setArguments([
            new Reference(self::SERVER_INFO_ID),
            new Reference(self::OPTIONS_ID),
            $config['cache']['discover_ttl_ms'],
            $config['cache']['tools_list_ttl_ms'],
            $config['cache']['templates_list_ttl_ms'],
            $config['cache']['resources_list_ttl_ms'],
        ]);

        $container->register(McpCallContextProvider::class, McpCallContextProvider::class)
            ->setArguments([new Reference('request_stack')])
            ->setPublic(true);

        $container->setDefinition(McpEndpointController::class, (new Definition(McpEndpointController::class))
            ->setArguments([
                new Reference($config['hooks']['authenticator']),
                new Reference($config['hooks']['rate_limiter'] ?? DenyingMcpRateLimiter::class),
                new Reference($config['hooks']['availability'] ?? AlwaysAvailable::class),
                new Reference(McpFirewallGuard::class),
                new Reference(McpDispatcher::class),
                new Reference(self::OPTIONS_ID),
                new Reference('nowo_mcp_kit.clock'),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true)
            ->addTag('controller.service_arguments'));
    }
}

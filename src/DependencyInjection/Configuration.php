<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\DependencyInjection;

use Nowo\McpKitBundle\Protocol\McpProtocol;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function array_diff;
use function array_unique;
use function count;
use function implode;
use function is_string;
use function preg_match;
use function strlen;

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
                ->arrayNode('server_info')
                    ->info('serverInfo: from configuration only (container parameters allowed), never request data.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('name')->defaultValue('app')
                            ->validate()->ifTrue(static fn (mixed $v): bool => !self::isLabel($v))->thenInvalid('nowo_mcp_kit.server_info.name must be at most 128 printable characters.')->end()
                        ->end()
                        ->scalarNode('title')->defaultNull()
                            ->validate()->ifTrue(static fn (mixed $v): bool => $v !== null && !self::isLabel($v))->thenInvalid('nowo_mcp_kit.server_info.title must be at most 128 printable characters.')->end()
                        ->end()
                        ->scalarNode('version')->defaultValue('0.0.0')
                            ->validate()->ifTrue(static fn (mixed $v): bool => !self::isLabel($v))->thenInvalid('nowo_mcp_kit.server_info.version must be at most 128 printable characters.')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('allowed_origins')
                    ->info('Origins (scheme://host[:port]) allowed to call the endpoint. Empty: any request with an Origin header gets 403.')
                    ->scalarPrototype()
                        ->validate()
                            ->ifTrue(static fn (mixed $v): bool => !is_string($v) || preg_match('#^https?://[A-Za-z0-9.-]+(:[0-9]{1,5})?$#', $v) !== 1)
                            ->thenInvalid('Each nowo_mcp_kit.allowed_origins entry must be scheme://host[:port].')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('legacy')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Serve legacy (initialize-based) clients statelessly.')
                            ->defaultTrue()
                        ->end()
                        ->arrayNode('versions')
                            ->info('Legacy revisions served, newest first.')
                            ->scalarPrototype()->end()
                            ->defaultValue(McpProtocol::LEGACY_VERSIONS)
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => $v === [] || array_diff($v, McpProtocol::LEGACY_VERSIONS) !== [] || count(array_unique($v)) !== count($v))
                                ->thenInvalid('nowo_mcp_kit.legacy.versions must be a non-empty subset of ' . implode(', ', McpProtocol::LEGACY_VERSIONS) . '.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('cache')
                    ->info('ttlMs caching hints (cacheScope is always "private").')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('discover_ttl_ms')->defaultValue(300000)->min(0)->end()
                        ->integerNode('tools_list_ttl_ms')->defaultValue(300000)->min(0)->end()
                        ->integerNode('templates_list_ttl_ms')->defaultValue(300000)->min(0)->end()
                        ->integerNode('resources_list_ttl_ms')->defaultValue(300000)->min(0)->end()
                    ->end()
                ->end()
                ->arrayNode('limits')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_body_bytes')->info('Request body cap (413 above).')->defaultValue(1048576)->min(1024)->end()
                        ->integerNode('max_json_depth')->info('JSON nesting cap (400 -32700 above).')->defaultValue(64)->min(8)->max(512)->end()
                        ->integerNode('max_text_chars')->info('Default page size of McpTextSlice.')->defaultValue(100000)->min(1)->end()
                        ->integerNode('max_list_rows')->info('Default page size of list tools.')->defaultValue(50)->min(1)->end()
                        ->integerNode('max_result_bytes')->info('Serialized tool result cap (OUTPUT_001 above).')->defaultValue(2097152)->min(1024)->end()
                        ->integerNode('slow_call_ms')->info('Calls taking at least this long are logged at warning level (0: every call).')->defaultValue(2000)->min(0)->end()
                    ->end()
                ->end()
                ->integerNode('maintenance_retry_after')
                    ->info('Retry-After seconds of the 503 answered in maintenance.')
                    ->defaultValue(60)
                    ->min(1)
                ->end()
                ->booleanNode('firewall_check')
                    ->info('Refuse (500) when the firewall user is not the principal user or the firewall is not secured.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('hooks')
                    ->info('Service ids of the host hooks.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('authenticator')->info('McpAuthenticatorInterface; required when enabled.')->defaultNull()->end()
                        ->scalarNode('authorizer')->info('McpToolAuthorizerInterface; default: no tool allowed.')->defaultNull()->end()
                        ->scalarNode('rate_limiter')->info('McpRateLimiterInterface; default: every request 429.')->defaultNull()->end()
                        ->scalarNode('availability')->info('McpAvailabilityInterface; default: always available.')->defaultNull()->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * Whether a value is a printable label of at most 128 characters (empty allowed: env placeholders are validated
     * with empty dummy values).
     *
     * @param mixed $value the value
     *
     * @return bool true when valid
     */
    private static function isLabel(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= 512 && preg_match('/^[^\p{C}]{0,128}$/u', $value) === 1;
    }
}

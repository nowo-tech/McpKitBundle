<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle;

use Nowo\McpKitBundle\DependencyInjection\NowoMcpKitExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use function dirname;

/**
 * Model Context Protocol (MCP) server for Symfony: stateless Streamable HTTP with JSON answers, host-provided token
 * authentication and a compiled tool registry. The host registers the tools; the bundle knows no domain.
 *
 * The bundle root (`@NowoMcpKitBundle`) is the package root, so the route import is
 * `@NowoMcpKitBundle/config/routes/mcp.yaml`.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class NowoMcpKitBundle extends Bundle
{
    /**
     * Bundle extension (`nowo_mcp_kit`).
     *
     * @return ExtensionInterface the extension
     */
    public function getContainerExtension(): ExtensionInterface
    {
        return new NowoMcpKitExtension();
    }

    /**
     * Package root, so `config/routes/*.yaml` resolve from `@NowoMcpKitBundle`.
     *
     * @return string absolute package root path
     */
    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}

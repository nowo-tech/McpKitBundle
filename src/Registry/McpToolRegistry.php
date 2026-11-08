<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Registry;

use Nowo\McpKitBundle\DependencyInjection\Compiler\McpRegistryPass;
use Nowo\McpKitBundle\Tool\McpToolDefinition;
use Nowo\McpKitBundle\Tool\McpToolInterface;
use Nowo\McpKitBundle\Tool\McpToolKind;
use Psr\Container\ContainerInterface;

use function array_map;
use function array_values;

/**
 * The tools compiled by {@see McpRegistryPass}: definitions in list
 * order (priority descending, then name) and a locator for the tool services.
 */
final readonly class McpToolRegistry
{
    /** @var array<string, McpToolDefinition> */
    private array $definitions;

    /**
     * Creates the registry.
     *
     * @param list<array{name: string, title: string, description: string, kind: string, idempotent: bool, inputSchema: array<string, mixed>, outputSchema: array<string, mixed>, requires: list<string>, openWorld: bool, priority: int}> $definitions compiled definitions, in list order
     * @param ContainerInterface $tools tool services by name
     */
    public function __construct(
        array $definitions,
        private ContainerInterface $tools,
    ) {
        $byName = [];
        foreach ($definitions as $definition) {
            $byName[$definition['name']] = new McpToolDefinition(
                $definition['name'],
                $definition['title'],
                $definition['description'],
                McpToolKind::from($definition['kind']),
                $definition['idempotent'],
                $definition['inputSchema'],
                $definition['outputSchema'],
                $definition['requires'],
                $definition['openWorld'],
                $definition['priority'],
            );
        }
        $this->definitions = $byName;
    }

    /**
     * Every definition, in list order.
     *
     * @return list<McpToolDefinition> the definitions
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    /**
     * A definition by name.
     *
     * @param string $name the tool name
     *
     * @return McpToolDefinition|null the definition, or null when unknown
     */
    public function get(string $name): ?McpToolDefinition
    {
        return $this->definitions[$name] ?? null;
    }

    /**
     * The tool service.
     *
     * @param string $name a known tool name
     *
     * @return McpToolInterface the tool
     */
    public function tool(string $name): McpToolInterface
    {
        /** @var McpToolInterface $tool */
        $tool = $this->tools->get($name);

        return $tool;
    }

    /**
     * The compiled form of a definition (container argument).
     *
     * @param McpToolDefinition $definition the definition
     *
     * @return array{name: string, title: string, description: string, kind: string, idempotent: bool, inputSchema: array<string, mixed>, outputSchema: array<string, mixed>, requires: list<string>, openWorld: bool, priority: int}
     */
    public static function compile(McpToolDefinition $definition): array
    {
        return [
            'name'         => $definition->name,
            'title'        => $definition->title,
            'description'  => $definition->description,
            'kind'         => $definition->kind->value,
            'idempotent'   => $definition->idempotent,
            'inputSchema'  => $definition->inputSchema,
            'outputSchema' => $definition->outputSchema,
            'requires'     => array_values(array_map(strval(...), $definition->requires)),
            'openWorld'    => $definition->openWorld,
            'priority'     => $definition->priority,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Registry;

use Nowo\McpKitBundle\DependencyInjection\Compiler\McpRegistryPass;
use Nowo\McpKitBundle\Tool\McpResourceTemplateDefinition;
use Nowo\McpKitBundle\Tool\McpResourceTemplateInterface;
use Psr\Container\ContainerInterface;

use function array_map;
use function array_values;

/**
 * The resource templates compiled by {@see McpRegistryPass}, in match
 * and list order (priority descending, then name).
 */
final readonly class McpResourceTemplateRegistry
{
    /** @var list<McpResourceTemplateDefinition> */
    private array $definitions;

    /**
     * Creates the registry.
     *
     * @param list<array{name: string, uriTemplate: string, title: string, description: string, variables: array<string, string>, outputSchema: array<string, mixed>, requires: list<string>, priority: int}> $definitions compiled definitions, in order
     * @param ContainerInterface $templates template services by name
     */
    public function __construct(
        array $definitions,
        private ContainerInterface $templates,
    ) {
        $this->definitions = array_map(static fn (array $definition): McpResourceTemplateDefinition => new McpResourceTemplateDefinition(
            $definition['name'],
            $definition['uriTemplate'],
            $definition['title'],
            $definition['description'],
            $definition['variables'],
            $definition['outputSchema'],
            $definition['requires'],
            $definition['priority'],
        ), $definitions);
    }

    /**
     * Every definition, in order.
     *
     * @return list<McpResourceTemplateDefinition> the definitions
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * The template service.
     *
     * @param string $name a known template name
     *
     * @return McpResourceTemplateInterface the template
     */
    public function template(string $name): McpResourceTemplateInterface
    {
        /** @var McpResourceTemplateInterface $template */
        $template = $this->templates->get($name);

        return $template;
    }

    /**
     * The compiled form of a definition (container argument).
     *
     * @param McpResourceTemplateDefinition $definition the definition
     *
     * @return array{name: string, uriTemplate: string, title: string, description: string, variables: array<string, string>, outputSchema: array<string, mixed>, requires: list<string>, priority: int}
     */
    public static function compile(McpResourceTemplateDefinition $definition): array
    {
        return [
            'name'         => $definition->name,
            'uriTemplate'  => $definition->uriTemplate,
            'title'        => $definition->title,
            'description'  => $definition->description,
            'variables'    => $definition->variables,
            'outputSchema' => $definition->outputSchema,
            'requires'     => array_values(array_map(strval(...), $definition->requires)),
            'priority'     => $definition->priority,
        ];
    }
}

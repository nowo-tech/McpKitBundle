# Installation

```bash
composer require nowo-tech/mcp-kit-bundle
```

Without Symfony Flex, register the bundle:

```php
// config/bundles.php
return [
    // ...
    Nowo\McpKitBundle\NowoMcpKitBundle::class => ['all' => true],
];
```

Then create `config/packages/nowo_mcp_kit.yaml` (see [CONFIGURATION.md](CONFIGURATION.md)).

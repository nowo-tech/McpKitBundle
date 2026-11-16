# Installation

```bash
composer require nowo-tech/mcp-kit-bundle
```

With Symfony Flex, the recipe in `.symfony/recipe/nowo-tech/mcp-kit-bundle/1.0/` registers the bundle and copies two
files:

- `config/packages/nowo_mcp_kit.yaml`: `enabled: false` plus commented `hooks` (authenticator, authorizer,
  rate limiter) to fill in;
- `config/routes/nowo_mcp_kit.yaml`: imports `@NowoMcpKitBundle/config/routes/mcp.yaml` (the endpoint is empty
  while `enabled` is false; keep it outside any locale prefix).

Without Symfony Flex, register the bundle:

```php
// config/bundles.php
return [
    // ...
    Nowo\McpKitBundle\NowoMcpKitBundle::class => ['all' => true],
];
```

Then create `config/packages/nowo_mcp_kit.yaml` and `config/routes/nowo_mcp_kit.yaml` with the content above (see [CONFIGURATION.md](CONFIGURATION.md)).

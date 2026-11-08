# MCP Kit Bundle

[![CI](https://github.com/nowo-tech/McpKitBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/McpKitBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/mcp-kit-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/mcp-kit-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/mcp-kit-bundle.svg)](https://packagist.org/packages/nowo-tech/mcp-kit-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/McpKitBundle.svg?style=social&label=Star)](https://github.com/nowo-tech/McpKitBundle) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/mcp-kit-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/McpKitBundle) so more developers can find it.

**MCP Kit Bundle** — a [Model Context Protocol](https://modelcontextprotocol.io) server endpoint for Symfony apps, so AI agents (Claude Code, Cursor, VS Code…) can call **your** tools with a personal token. Stateless Streamable HTTP with JSON answers only, protocol revisions **2026-07-28** (modern) and **2025-11-25** / **2025-06-18** (legacy) on one endpoint, authentication and authorization through your own hooks, a tool registry compiled at build time with JSON Schema validation of arguments and results, and an `untrusted` envelope that keeps content written by people apart from protocol text. The bundle knows no domain: you register the tools. Supports Symfony **7.4**, **8.0** and **8.1** (CI: 7.4 and 8.1) · PHP 8.2+ (Symfony 8.x requires PHP 8.4+).

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly**.

Workers that do **not** reset the kernel between requests are supported too: services are readonly, the registry is compiled into the container, and per-request data lives on the request. See [docs/FRANKENPHP-WORKER-AUDIT.md](docs/FRANKENPHP-WORKER-AUDIT.md).

## Features

- **Streamable HTTP, stateless:** one `POST` endpoint, JSON answers, no sessions, no SSE; `Mcp-Session-Id` is never issued.
- **Dual-era:** modern per-request `_meta` (2026-07-28) and the legacy `initialize` handshake (2025-11-25, 2025-06-18) on the same endpoint, with the header checks (`MCP-Protocol-Version`, `Mcp-Method`, `Mcp-Name`) the spec requires.
- **Fail-closed pipeline:** `Origin` allowlist (DNS rebinding), method, body size, bearer credential, token budget, media types, JSON-RPC shape, version and headers, in a fixed order.
- **Your tokens:** an authenticator hook returns a principal per request; an authorizer hook decides which tools a token sees; a rate-limiter hook holds the budgets. No OAuth.
- **Compiled tool registry:** static tool definitions validated at container build (names, kinds, JSON Schema rules), explicit annotations, destructive tools hidden without the token's tick.
- **Untrusted envelope:** content written by people goes under `untrusted`, behind a fixed notice, JSON-escaped, with invisible characters shown as escapes.
- **Limits and audit:** body, depth, text, list and result caps; a write budget; an audit event per call with ids only.

## Installation

```bash
composer require nowo-tech/mcp-kit-bundle
```

With Symfony Flex the recipe adds `config/packages/nowo_mcp_kit.yaml` and the route import. Without Flex, see [docs/INSTALLATION.md](docs/INSTALLATION.md).

## Quick start

```yaml
# config/packages/nowo_mcp_kit.yaml
nowo_mcp_kit:
    enabled: true
```

See [docs/USAGE.md](docs/USAGE.md) for the hooks and the first tool.

## Requirements

- PHP 8.2+ (`>=8.2 <8.6`)
- Symfony 7.4 or 8.x
- A stateless firewall in front of the endpoint that turns the bearer token into the security user (recommended: `symfony/security-bundle`)

## Configuration

See [docs/CONFIGURATION.md](docs/CONFIGURATION.md).

## Usage

See [docs/USAGE.md](docs/USAGE.md).

## Demo

- `demo/symfony8` — Symfony **8.1** (FrankenPHP PHP **8.5**, worker mode); host port **8219** by default.

The demo serves note tools (`note_list`, `note_get`, `note_create`, `note_archive`) and the `demo://note/{id}` template
with throwaway tokens, a seeded note full of injection text, and a conformance profile for the official MCP runner.

```bash
make -C demo up-symfony8          # http://localhost:8219 (client setup on the home page)
make demo-smoke                   # boot, HTTP 200, down (REQ-TEST-011)
make test-conformance             # official MCP conformance runner (pinned), 2026-07-28 and 2025-11-25
```

Runtime mode is **`FRANKENPHP_MODE`** (`worker` default, or `classic`) — see [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md).

## Development

```bash
make up
make install
make test
make cs-check
make phpstan
make igor
make test-coverage-100
make release-check
```

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [Architecture](docs/ARCHITECTURE.md)
- [PSR evaluation (REQ-CS-007)](docs/PSR.md)
- [Demo (FrankenPHP)](docs/DEMO-FRANKENPHP.md)
- [FrankenPHP worker audit (kernel not reset)](docs/FRANKENPHP-WORKER-AUDIT.md)
- [GitHub Actions CI requirements](docs/GITHUB_CI.md)

## Tests and coverage

- Tests: PHPUnit (PHP) — unit tests plus kernel integration tests (every pipeline step, both eras, tools, resources,
  worker mode); the official MCP conformance runner against the demo (required CI gate, per-check baseline)
- PHP: 100% (gate enforced in CI by `composer test-coverage-100`, no exclusions)
- TS/JS: N/A
- Python: N/A

## License and author

MIT · [Nowo.tech](https://nowo.tech) · [Héctor Franco Aceituno](https://github.com/HecFranco)

# Demo with FrankenPHP

This bundle includes a runnable demo with FrankenPHP in:

- `demo/symfony8` — Symfony **8.1** on FrankenPHP **PHP 8.5** (`dunglas/frankenphp:1-php8.5-alpine`)

**PHP version policy (Symfony 8 demos):** use the newest PHP minor published in official FrankenPHP images when the demo’s `require.php` and Symfony 8 allow it (today: **8.5**).

The demo uses:

- Caddy on HTTP `:80` inside the container
- **`Caddyfile`**: **worker** mode (`php_server { worker ... }`)
- **`Caddyfile.dev`**: classic `php_server` (**no worker**) — hot-reload friendly

Runtime selection is via **`FRANKENPHP_MODE`** (`classic` | `worker`), not `APP_ENV`.

## Table of contents

- [Quick start](#quick-start)
- [Switching classic vs worker (`FRANKENPHP_MODE`)](#switching-classic-vs-worker-frankenphp_mode)
- [Worker mode and kernel reset](#worker-mode-and-kernel-reset)
- [Timeout hierarchy](#timeout-hierarchy)
- [Development stack in demos](#development-stack-in-demos)
- [Demo smoke (REQ-TEST-011)](#demo-smoke-req-test-011)

## Quick start

From the bundle root:

```bash
make -C demo up-symfony8
```

Then open `http://localhost:8219`. The port comes from `PORT` in `demo/symfony8/.env` (copied from `.env.example`).

## Switching classic vs worker (`FRANKENPHP_MODE`)

Set `FRANKENPHP_MODE=classic` or `worker` in `demo/symfony8/.env`, then recreate the container (`docker compose up -d`).
The entrypoint copies `Caddyfile.dev` (classic) or keeps `Caddyfile` (worker).

## Worker mode and kernel reset

The bundle keeps no state between requests; see [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

## Timeout hierarchy

| Layer | Value |
| --- | --- |
| PHP `max_execution_time` | 20 s |
| Caddy write timeout | 30 s |
| FrankenPHP `max_wait_time` (free thread) | 10 s |

The bundle makes no outbound I/O; MCP answers are JSON only (no stream holds a worker).

## Development stack in demos

- Symfony Web Profiler and debug mode
- `nowo-tech/twig-inspector-bundle` and `nowo-tech/hot-reload-bundle` (dev only)

## Demo smoke (REQ-TEST-011)

```bash
make demo-smoke
```

Boots the demo, expects HTTP 200 on `http://127.0.0.1:8219`, then stops it.

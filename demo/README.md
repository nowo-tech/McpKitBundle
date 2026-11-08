# MCP Kit Bundle Demo

This directory contains a runnable demo for:

- `symfony8` — Symfony **8.1**, FrankenPHP PHP **8.5** (http://localhost:8219)

## Quick start

```bash
make up-symfony8
```

The demo includes:

- FrankenPHP with Caddy (HTTP on `:80` inside container). Mode via **`FRANKENPHP_MODE`** (`worker` default, or `classic`); see [docs/DEMO-FRANKENPHP.md](../docs/DEMO-FRANKENPHP.md).
- No database: demo data lives in the filesystem cache of the container.
- Web Profiler, Twig Inspector and Hot Reload enabled in `dev`
- Dedicated `Makefile` (`demo/symfony8/Makefile`)

## Release checks

```bash
make release-check
# or smoke only: make -C .. demo-smoke
```

This updates the path bundle, runs demo PHPUnit, and boots the stack to assert **HTTP 200** on `:8219` (`demo-smoke` / REQ-TEST-011).

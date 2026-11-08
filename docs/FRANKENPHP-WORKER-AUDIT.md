# FrankenPHP worker audit (kernel not reset)

The bundle is safe when FrankenPHP keeps the Symfony kernel warm **without** resetting it between requests.

## Table of contents

- [What keeps state](#what-keeps-state)
- [Per-request data](#per-request-data)
- [Checks in CI](#checks-in-ci)
- [Host responsibilities](#host-responsibilities)

## What keeps state

Nothing that depends on a request. Every service is `final readonly`:

| Service | Holds |
| --- | --- |
| `McpEndpointController`, `McpDispatcher`, `McpToolInvoker`, `McpResourceReader` | Injected services and configuration only |
| `McpToolRegistry`, `McpResourceTemplateRegistry` | Definitions compiled into the container at build time (same for every request) |
| `McpEndpointOptions`, `McpServerInfo`, `McpLimits` | Configuration |
| `McpFirewallGuard` | The token storage and the security helper (Symfony resets the token per request) |
| `McpCallContextProvider` | The request stack |

The JSON Schema validator is built per validation, so no cache crosses principals. No static mutable state exists.

## Per-request data

The principal, the rate-limit state and the `McpCallContext` live on `Request::$attributes`, so they end with the
request. The kit never opens a session and keeps no state handle.

## Checks in CI

- `WorkerModeTest`: two tokens alternate for 250 rounds on one kernel; each sees only its own tools, and every audit
  event carries the right token.
- Igor (`composer igor`) and `nowo-tech/phpstan-frankenphp` (worker-strict ruleset). The one Igor hint
  (`registerFailedToken()`, a call into the host's limiter storage) is annotated.
- The conformance gate runs the demo in worker mode.

## Host responsibilities

Host hooks and tools must be stateless too: keep budgets, tokens and idempotency keys in shared storage (database,
cache), never in service properties.

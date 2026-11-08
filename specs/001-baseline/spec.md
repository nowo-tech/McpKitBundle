# Feature Specification: McpKitBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-11-08  
**Status**: Active  

**Package**: `nowo-tech/mcp-kit-bundle`  
**Configuration root**: `nowo_mcp_kit`  
**Code inventory**: [`code-inventory.md`](code-inventory.md)  
**Integrator spec**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md)  
**Source contract**: Beacon spec 219 (MCP server), contract v1.0 (decisions of 2026-11-08)

---

## Summary

A Model Context Protocol server endpoint for Symfony hosts. The bundle supplies the transport (stateless Streamable
HTTP, JSON only), protocol versions (modern 2026-07-28, legacy 2025-11-25 and 2025-06-18), the order of refusals,
host hooks for authentication, authorization, budgets and availability, a compiled tool registry with JSON Schema
validation, the untrusted envelope, size caps and an audit event. The host registers the tools.

## Requirements

### Bundle and DI

- **FR-BUNDLE-001** The bundle path is the package root; the extension alias is `nowo_mcp_kit`.
- **FR-CFG-001** `enabled` (default false) and `path` (default `/mcp`, absolute) configure the endpoint; with
  `enabled: false` nothing reachable is registered.
- **FR-CFG-002** `server_info`, `allowed_origins`, `legacy`, `cache`, `limits`, `maintenance_retry_after`,
  `firewall_check` and `hooks` are validated strictly; `hooks.authenticator` is required when enabled.
- **FR-ROUTE-001** `config/routes/mcp.yaml` imports the `nowo_mcp_kit` route loader: one stateless route for every
  method at `path`, or nothing when disabled.

### Transport (HTTP)

- **FR-HTTP-001** POST only (405 `Allow: POST` otherwise); JSON answers only; no session id issued or honoured.
- **FR-HTTP-002** Fixed order of checks (docs/USAGE.md "Order of checks"); authentication and the token budget run
  before the body is parsed.
- **FR-HTTP-003** `Origin` allowlist (403), `Content-Type` (415), `Accept` (406), body cap (413).
- **FR-HTTP-004** Notifications answer 202 with no body.
- **FR-HTTP-005** Every answer carries `Cache-Control: no-store, private`, `X-Content-Type-Options: nosniff` and the
  rate-limit headers once known.

### Protocol

- **FR-PROTO-001** Modern requests carry `_meta` protocol version and client capabilities; the version header must
  match (`-32020`); unsupported versions answer `-32022` with the supported list.
- **FR-PROTO-002** Legacy `initialize` negotiates a legacy version without a session; legacy requests carry the
  version header.
- **FR-PROTO-003** `Mcp-Method` and `Mcp-Name` (Base64 sentinel decoded) must match the body.
- **FR-PROTO-004** Methods: `server/discover` (modern), `initialize` and `ping` (legacy), tools and resources; anything
  else `-32601` (modern 404, legacy 200). Modern results carry `resultType`, `serverInfo` in `_meta`, and caching hints
  on cacheable results.

### Security hooks

- **FR-SEC-001** The host authenticator answers a principal or a failure per request; failed tokens are counted per IP.
- **FR-SEC-002** The firewall user must be the principal's user behind a secured firewall (`firewall_check`).
- **FR-SEC-003** Budgets: failed tokens per IP, then the token budget, each 429 `-31001` with `data.limit` and
  `Retry-After`.
- **FR-SEC-004** Availability: disabled 404 (empty), maintenance 503 `-31005`.
- **FR-CTX-001** `McpCallContext` (era, version, sanitised client, cleaned User-Agent) lives on the request.

### Tools

- **FR-TOOL-001** Tools are services with a static definition; the registry is compiled at build time and the build
  fails on an invalid or duplicate definition (`McpSchemaRules`).
- **FR-TOOL-002** `tools/list` lists, in a fixed order, the tools the authorizer allows; destructive tools need the
  principal's tick.
- **FR-TOOL-003** `tools/call` order: unknown name, arguments shape, tick / authorizer (`TOKEN_001`), unknown argument
  (`INPUT_001`), schema (`INPUT_002`), audit listener (`AUDIT_001`), write budget (429), tool, output schema, result cap
  (`OUTPUT_001`).
- **FR-TOOL-004** Business errors are `isError` results `{ECODE, err}` with extra fields; other failures `-32603`,
  except after a committed write, which answers a success with its ids and a fixed notice.
- **FR-ENV-001** Untrusted groups are emitted under `untrusted` behind the fixed notice; the text block escapes hidden
  characters.
- **FR-AUDIT-001** One `McpToolCallEvent` per call that reaches the registry, ids and field names only; a throwing
  listener does not change the answer.

### Resources and paging

- **FR-RES-001** Resource templates are services with a static definition (RFC 6570 level 1, one regex per variable),
  checked at build time; `resources/templates/list` lists those the authorizer allows; `resources/list` is empty.
- **FR-RES-002** `resources/read` matches allowed templates in order; unmatched, hidden or refused URIs answer the same
  `-32602`; contents carry the envelope (`notice` first) and `ttlMs: 0`.
- **FR-PAGE-001** `McpTextSlice` pages text by characters; `McpCursor` encodes and strictly decodes opaque cursors;
  `McpLimits` exposes the configured page sizes.

# Security

## Table of contents

- [Threat model](#threat-model)
- [Transport](#transport)
- [Authentication](#authentication)
- [Tools](#tools)
- [Untrusted content](#untrusted-content)
- [Logging](#logging)
- [Conformance](#conformance)
- [Host checklist](#host-checklist)
- [Audit status](#audit-status)

## Threat model

The endpoint serves AI agents with personal tokens. Risks: token theft and replay, DNS rebinding from a browser,
flooding, a client that lies about who it is, and content written by people that tries to steer an agent (prompt
injection). The bundle cannot prevent prompt injection; it labels data as data and adds no instruction of its own.

## Transport

- POST only; JSON answers only; no session, no state handle, no SSE.
- `Origin` allowlist, empty by default: a browser request carrying `Origin` gets 403 before anything else. No CORS.
- Authentication and the token budget run before the body is parsed; the body is capped (Content-Length and streamed).
- Mirrored headers (`MCP-Protocol-Version`, `Mcp-Method`, `Mcp-Name`) must match the body; messages never echo values.

## Authentication

- The credential is read from `Authorization` only (never the query string), never logged or echoed.
- Failed tokens are counted per IP and limited before the authenticator runs.
- The firewall user must be the principal's user behind a secured, stateless firewall (`firewall_check`, which can only
  be turned off with `kernel.debug`).
- Bearer only, no cookies: CSRF does not apply (REQ-SEC-005).

## Tools

- Definitions are static and checked at build time: no user content in names, titles, descriptions or schemas.
- Arguments are validated against closed schemas (unknown arguments refused); outputs are validated before sending.
- Destructive tools are hidden and refused without the principal's tick, whatever the authorizer says.
- Writes need an audit listener and pass the write budget before the tool runs.
- Schemas resolve only local `$defs`; nothing is fetched.
- A committed write is never reported as an error, so an agent is not invited to repeat it.
- Tools receive the whole `Request` (through `McpToolCall`); they must not read credentials from it.
- A tool declared `ReadOnly` that writes skips the audit gate and the write budget: the kit cannot detect it, so host
  review must.

## Untrusted content

Content written by people is returned under `untrusted`, behind a fixed notice from code, JSON-escaped, with control,
format and other invisible characters shown as escapes. The build refuses output schemas that could carry free text
outside `untrusted` (any wildcard pattern, free formats, non-http(s) URIs). Marking is defence in depth, never a guarantee. Hosts should advise: one token per agent,
read-only tokens for anything unattended, the destructive tick only when needed, approval prompts in the client.

## Logging

Logs carry tool names, outcomes, durations and exception classes only; never bodies, arguments, results, credentials
or `clientInfo`.

## Conformance

The official MCP conformance runner is a required CI gate (both revisions), including its DNS-rebinding scenario: a
foreign `Host` / `Origin` is refused and the demo's own origin is accepted.

## Host checklist

- Cap the request body at the proxy too (Caddy `request_body max_size`, nginx `client_max_body_size`): the kit reads at
  most `max_body_bytes`, but the SAPI may have buffered more.
- Do not read the request body in a listener that runs before the endpoint (body logging, error reporting): the kit
  authenticates and checks the budget before parsing, and an earlier reader defeats that.
- Configure `framework.trusted_proxies`: failed tokens are counted per client IP, and behind an untrusted proxy every
  client shares one bucket, so a stranger could lock everyone out.
- Intermediaries that route or rate-limit on `Mcp-Method` / `Mcp-Name` must refuse legacy versions, which do not need
  to send them (MCP 2026-07-28 note).

## Audit status

Audit IH (REQ-SEC-004), S1 and S2: Pass / Low. Fixed: IH-L1 (stateless firewall), IH-L2 (committed writes),
IH-L3 (free-text loopholes), IH-L4 (escape set), IH-I1, IH-I3, IH-I4, IH-I5, IH-I7. IH-I2 and IH-I6 accepted as
documented. Re-verification pending.

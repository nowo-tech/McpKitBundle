# Security

## Table of contents

- [Threat model](#threat-model)
- [Transport](#transport)
- [Authentication](#authentication)
- [Logging](#logging)
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
  be turned off with `kernel.debug`). On a refusal the token is dropped, so a stateful firewall stores no session.
- Bearer only, no cookies: CSRF does not apply (REQ-SEC-005).

## Logging

Logs carry exception classes only; never bodies, arguments, results, credentials or `clientInfo`.

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

Audit IH (REQ-SEC-004), S1: Pass / Low; IH-L1 (stateless firewall), IH-I1, IH-I3, IH-I5 (negotiated legacy version)
and IH-I7 fixed — re-verification pending.

# Security

## Table of contents

- [Threat model](#threat-model)
- [Transport](#transport)
- [Authentication](#authentication)
- [Tools](#tools)
- [Untrusted content](#untrusted-content)
- [Logging](#logging)
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
- The firewall user must be the principal's user behind a secured firewall (`firewall_check`).
- Bearer only, no cookies: CSRF does not apply (REQ-SEC-005).

## Tools

- Definitions are static and checked at build time: no user content in names, titles, descriptions or schemas.
- Arguments are validated against closed schemas (unknown arguments refused); outputs are validated before sending.
- Destructive tools are hidden and refused without the principal's tick, whatever the authorizer says.
- Writes need an audit listener and pass the write budget before the tool runs.
- Schemas resolve only local `$defs`; nothing is fetched.

## Untrusted content

Content written by people is returned under `untrusted`, behind a fixed notice from code, JSON-escaped, with hidden
characters shown as escapes. Marking is defence in depth, never a guarantee. Hosts should advise: one token per agent,
read-only tokens for anything unattended, the destructive tick only when needed, approval prompts in the client.

## Logging

Logs carry tool names, outcomes, durations and exception classes only; never bodies, arguments, results, credentials
or `clientInfo`.

## Audit status

Not audited yet. An independent REQ-SEC-004 audit runs before 1.0.0 (re-verification pending).

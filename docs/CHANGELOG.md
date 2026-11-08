# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Repository scaffold: package `nowo-tech/mcp-kit-bundle`, bundle class, `nowo_mcp_kit` configuration root (`enabled`,
  `path`), Docker / Makefile / CI, FrankenPHP demo, Spec Kit baseline and documentation.
- Streamable HTTP endpoint (`Http\McpEndpointController`, route loader `type: nowo_mcp_kit`,
  `config/routes/mcp.yaml`): POST only, JSON answers, no session, fixed order of checks (availability, `Origin`,
  method, maintenance, `Content-Length`, failed tokens per IP, credential, authenticator, firewall user, token budget,
  `Content-Type`, `Accept`, body cap, JSON, JSON-RPC shape, notifications 202, era / version, mirrored headers).
- Dual-era protocol: modern `2026-07-28` (`_meta` per request, `server/discover`, `resultType`, `ttlMs`,
  `cacheScope: private`) and legacy `2025-11-25` / `2025-06-18` (`initialize` without session, `ping`).
- Host hooks: `McpAuthenticatorInterface` (required), `McpRateLimiterInterface` (default refuses), `McpAvailabilityInterface`
  (default available); `McpPrincipal`, `McpCredential`, `McpFirewallGuard`; `McpCallContext` on the request.
- Configuration: `server_info`, `allowed_origins`, `legacy`, `cache`, `limits`, `maintenance_retry_after`,
  `firewall_check`, `hooks`.
- Audit IH (S1): the firewall guard also requires a stateless firewall (without SecurityBundle's helper: no started
  session); `firewall_check: false` is refused unless `kernel.debug`; `Accept` uses the most specific range
  (`application/json;q=0` refuses JSON); the legacy call context records the negotiated version. On a refusal the
  guard drops the security token (and empties a started session), so a stateful firewall sends no session cookie
  with the 500 (IH-L1r).
- Tool registry: `McpToolInterface` (static `definition()`, autoconfigured tag `nowo_mcp_kit.tool`),
  `McpToolDefinition`, `McpToolKind` (read-only / additive / destructive, explicit annotations), build-time
  `McpRegistryPass` + `McpSchemaRules` (closed schemas, bounded strings and arrays, free text only under `untrusted`,
  keyword whitelist, local `$ref`), `McpToolAuthorizerInterface` (default allows nothing).
- `tools/list` filtered per principal in a fixed order; `tools/call` with argument validation (opis/json-schema 2020-12,
  `INPUT_001` / `INPUT_002`), destructive tick, write gate (audit listener required, `consumeWrite` 429), output schema
  validation, result cap (`OUTPUT_001`), business errors as `isError` results (`McpToolError`).
- Untrusted envelope (`McpUntrusted`, `McpEnvelope`): fixed notice, `untrusted` objects, escaped text block (C1, bidi,
  zero-width and tag characters).
- `McpToolCallEvent` after every call (ids and field names only); `McpIdempotencyKey` for create tools.
- Audit IH (S2): a committed write whose result cannot be shown answers a success with its ids and a fixed notice
  (outcome `saved_without_result`); trusted output strings need safe anchored patterns, http(s) URIs or fixed formats;
  the output root is an object; the escape set covers every `\p{Cc}` / `\p{Cf}`, variation selectors and Hangul
  fillers; `McpToolError` and `McpResourceLink` validate their values; `McpKitEvents::TOOL_CALL`.
- Audit IH delta (S2): the saved-without-result answer is an error result `SAVED_001` (exempt from the output schema)
  with plain-ASCII ids only (IH-L2r, IH-I8); safe patterns refuse top-level alternation and mixed-class ranges, and a
  trusted URI pattern must be safe after its `https://` prefix (IH-L3r, IH-I10); `McpToolError` extras are scalars
  only and `INPUT_002` paths are cut to 400 characters (IH-I9); the escape set adds U+180B–180D, U+180F and `\p{Cn}`
  (IH-I11).
- Resource templates: `McpResourceTemplateInterface` (tag `nowo_mcp_kit.resource_template`),
  `McpResourceTemplateDefinition` (RFC 6570 level 1, one regex per variable, build-time checks),
  `resources/templates/list` per principal, `resources/read` (same not-found for every reason, envelope with `notice`,
  `ttlMs: 0`, audit event); `resources/list` always empty. The authorizer now receives tools and templates.
- Paging helpers: `McpTextSlice` (characters, `truncated` / `next_offset` / `total`), `McpCursor` (opaque base64url,
  strict decoding), `McpLimits` service.

# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed

- Dev: Igor 0.11 (REQ-CS-008) — the `Request` held by `McpToolCall`, `McpResourceRead` and `McpToolAttempt` belongs to per-call objects, never shared services; documented `@igor-ignore` (false positives). `igor-php/igor-php` accepts `^0.11.0` (supersedes #21).
- Demo: runs in UiKit's demo platform shell (`nowo-tech/ui-kit-bundle` ^2.60); the demo's own header is gone and the pages use the shell page headers and section cards. Demo only, no bundle change.
- CI: new `deps-floor` job (as in BreadcrumbKit). It checks that the demo lock installs every nowo-tech requirement of the bundle (`.scripts/check-demo-lock.py`), then downgrades nowo-tech/transaction-* to the lowest versions composer.json allows (`composer update 'nowo-tech/transaction-*' --prefer-lowest`) and runs PHPStan and the tests there, so a call to a newer API than the constraint allows fails the pull request.
- Demo: `make update-bundle` runs `composer update nowo-tech/mcp-kit-bundle -w`, so the bundle's new or raised requirements reach the demo lock too.
- Requires `nowo-tech/transaction-doctrine` ^1.8.1 (fixes GHSA-9wh3-m3m5-jffx and GHSA-65w2-9r4h-848g).
- With transaction-doctrine >= 1.8 a replayed write call no longer detaches the principal's user: the host's entity manager is restored instead of cleared (REQ-RUNTIME-003 rule 2), so after a failed principal refresh the original user stays managed and the audit listener links it. The test that asserted the old detached user now asserts the audit row is written and nothing stays queued.

## [1.2.0] - 2026-11-18

### Added

- Write tools run as write units inside the invoker (SYMFONY-BEACON spec 241 T020): with `write_unit.enabled: true` and
  nowo-tech/transaction-doctrine ^1.3, the execution of each additive or destructive tool runs inside
  transaction-doctrine's `WriteUnitInterface`, so a lock conflict replays the tool, not the request. Read-only tools are
  never wrapped. The checks, the write budget and the audit event stay outside the unit: one `consumeWrite` and one
  `McpToolCallEvent` per call, dispatched after the final outcome. When the retries run out the call answers JSON-RPC
  `-31007` (`McpProtocol::WRITE_CONFLICT`) with transaction-doctrine's conflict status (409 by default),
  `error.data = {code: "CONFLICT_001", retry_after: n}` and `Retry-After`; when the failed attempt had already
  committed (`ConcurrencyConflict::$committed`), the `isError` tool result `CONFLICT_002` with a fixed "saved, do not
  repeat; read it again" text and no retry hint, never a protocol error (audit IH-L2, SZ-L1). McpKit's own call data is
  rebuilt per attempt (a new `McpToolCall` with `$attempt`); the HTTP request, principal and host context are the same
  objects. New: `Write\McpWriteRunnerInterface`, `Write\McpWriteConflict`,
  `Bridge\TransactionDoctrine\TransactionDoctrineWriteRunner`, `JsonRpcError::writeConflict()`,
  `McpCallOutcome::Conflict`. Option off or package absent: unchanged (BC test)
- Principal refresh before the audit event (audit SZ-M1): after a replay or any conflict (the write unit cleared the
  entity managers, detaching `$user` and `$hostContext`), the invoker refreshes the principal through the new optional
  hook `hooks.principal_refresher` (`Security\McpPrincipalRefresherInterface`), else the firewall's user provider
  (`Security\UserProviderPrincipalRefresher`, `refreshUser()`). A failed refresh is logged and the event still goes
  out with the original principal. Listeners must refer to the principal by id or rely on the refreshed principal.
  The default refresher accepts the reloaded user only with the same class and `getUserIdentifier()` (SZ-L2);
  `hooks.principal_refresher` without `write_unit.enabled` is refused at build time (SZ-I6)
- `McpToolCallEvent::$attempts` (times the tool ran) and `McpToolCallEvent::$replayed`
- Demo: Playwright e2e (home page and a real `tools/list` call), `make test-e2e` / `make demo-screenshots` and one WebP screenshot in the README (REQ-DEMO-013)

### Changed

- Demo uses the UiKit base style (REQ-DEMO-014)
- Spec Kit baseline: user scenarios added to `specs/001-baseline/spec.md` and the five `src/` files added in 1.1.0 mapped in `code-inventory.md` (REQ-SPECKIT-001 / REQ-SPECKIT-003)
- Installation: the Flex recipe and the two files it copies are documented (REQ-RECIPE-001)

## [1.1.0] - 2026-11-11

Minor release (1.1.0): REQ-ARCH-001 rule 3. Every host-facing port, event and value object now has a variant without
HttpFoundation types; the `Request`-typed ones are deprecated and stay until 2.0.0. Standard hosts need no change.

### Added

- `Http\McpHttpRequest`: framework-free view of the endpoint request (method, path, scheme, host, client IP after
  `trusted_proxies`, headers; never the body), with `credential()` (same rules as before: `Authorization` only).
  Redaction is an allowlist (`Accept*`, `Content-Type`, `Content-Length`, `Origin`, `User-Agent`, `Host`,
  `X-Forwarded-For` / `-Proto` / `-Host` / `-Port` / `-Prefix`, `MCP-Protocol-Version`, `Mcp-Method`, `Mcp-Name` are
  shown in clear; exact names, no prefixes); the raw values of every other header are kept
  out of the object's properties (internal `WeakMap`), so VarDumper, `var_export()`, `print_r()`, `(array)`,
  `json_encode()` and `serialize()` show `[redacted]`. Restored or cloned copies carry no credential. Conflicting
  forwarding headers give `clientIp: null` instead of an exception, so the early refusals keep their status (audit
  MN-M-L1, L2, L3, I1).
- `Security\McpRequestAuthenticatorInterface::authenticateRequest(McpHttpRequest)` and
  `Security\McpRequestAvailabilityInterface::availabilityOf(McpHttpRequest)`: twins of the two host hooks. When a
  service implements both the old and the new port, the bundle calls the new one. Order of checks, deny-by-default,
  token checks, budgets and answers are unchanged (old-vs-new equivalence test over 26 scenarios, including a dual
  availability class, a trusted proxy and conflicting forwarding headers:
  `tests/Integration/Kernel/HostPortEquivalenceTest.php`).
- `McpToolCall::$http` / `McpResourceRead::$http` (`McpHttpRequest`), `McpCredential::fromHeader()`,
  `McpCallContext::fromAttribute()`.
- DDD-host kernel test (`tests/Integration/Kernel/DddHostKernelTest.php`); USAGE sections "Standard Symfony host" and
  "DDD host".

### Deprecated

- `McpAuthenticatorInterface`, `McpAvailabilityInterface` (use the `McpRequest*` twins), `McpCredential::fromRequest()`,
  `McpCallContext::fromRequest()`, `McpToolCall::$request`, `McpResourceRead::$request`, `AlwaysAvailable::state()`.
  All are removed in 2.0.0; see [UPGRADING](UPGRADING.md#upgrading-to-110).

## [1.0.0] - 2026-11-08

First release: a stateless MCP Streamable HTTP server for Symfony (spec 219). Transport, tool and resource registries, host authentication hooks, limits and audit; the tools stay in the host. Independent audit: Appendix IH, Pass / Info.

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
- Audit IH (S3): optional `McpCursorSigner` (HMAC from `kernel.secret`, bound to token and tool; IH-I12); template
  variable regexes may not contain PCRE verbs (IH-I13).
- Conformance: the official runner `@modelcontextprotocol/conformance` (pinned to 0.2.0-alpha.12) runs the
  `2026-07-28` and `2025-11-25` requirement sets against the demo (`make test-conformance`, required CI job), with
  per-check baselines in `tests/Conformance/`. `resources/read` not-found errors carry the requested URI in `data`
  (SEP-2164) when it is short visible ASCII.
- TypeScript SDK e2e (`@modelcontextprotocol/sdk` 1.32.2, pinned) in the same gate, including the `SAVED_001` answer.
- Final audit IH: safe patterns refuse inline options and `(?…` groups other than `(?:`, and PCRE verbs (IH-I14); the
  saved-write answer skips ids that are not strings or integers (IH-I15); the conformance runner runs from a lockfile
  and the Node image is pinned by digest (IH-I16); SECURITY.md updated (IH-I17).
- Demo conformance profile (`MCP_CONFORMANCE=1`), demo client setup page, and full documentation (usage, security,
  worker audit, upgrading).
- Paging helpers: `McpTextSlice` (characters, `truncated` / `next_offset` / `total`), `McpCursor` (opaque base64url,
  strict decoding), `McpLimits` service.

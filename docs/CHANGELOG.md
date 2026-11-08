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

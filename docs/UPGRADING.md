# Upgrading

## Table of contents

- [Adopting 1.0.0](#adopting-100)

## Adopting 1.0.0

First release. To serve MCP from an application:

1. `composer require nowo-tech/mcp-kit-bundle` (Flex adds `config/packages/nowo_mcp_kit.yaml` and the route import;
   otherwise import `@NowoMcpKitBundle/config/routes/mcp.yaml` outside any locale prefix).
2. Add a **stateless** firewall on the endpoint whose authenticator turns a valid bearer token into the security user
   and lets a bad token through with no user. No `access_control` rule on the path.
3. Implement the hooks and set `hooks.*`: `McpAuthenticatorInterface` (required), `McpToolAuthorizerInterface`,
   `McpRateLimiterInterface` (the default refuses everything), optionally `McpAvailabilityInterface`.
4. Register tools (`McpToolInterface`) and resource templates (`McpResourceTemplateInterface`); put text written by
   people under `untrusted`.
5. Listen to `McpToolCallEvent` (`McpKitEvents::TOOL_CALL`): additive and destructive tools are refused without a
   listener.
6. Set `enabled: true` and `server_info`; keep `allowed_origins` empty unless a browser client needs it.
7. Follow the host checklist in [SECURITY.md](SECURITY.md#host-checklist): proxy body cap, `trusted_proxies`, no
   early body readers, no logging of the endpoint's bodies.

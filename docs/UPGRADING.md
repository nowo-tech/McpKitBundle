# Upgrading

## Table of contents

- [Upgrading to 1.1.0](#upgrading-to-110)
- [Adopting 1.0.0](#adopting-100)

## Upgrading to 1.1.0

No action is required: 1.0 hooks keep working with the same answers. The `Request`-typed members are deprecated and
removed in 2.0.0. To migrate (one hook at a time is fine):

| Deprecated (removed in 2.0.0) | Replacement |
| --- | --- |
| `McpAuthenticatorInterface::authenticate(Request $request)` | `McpRequestAuthenticatorInterface::authenticateRequest(McpHttpRequest $request)` |
| `McpAvailabilityInterface::state(Request $request)` | `McpRequestAvailabilityInterface::availabilityOf(McpHttpRequest $request)` |
| `McpCredential::fromRequest($request)` | `$request->credential()` on `McpHttpRequest`, or `McpCredential::fromHeader($request->headers->get('Authorization'))` in a Symfony firewall authenticator |
| `McpCallContext::fromRequest($request)` | `$call->context` in a tool, the `McpCallContextProvider` service, or `McpCallContext::fromAttribute($request->attributes->get(McpCallContext::ATTRIBUTE))` |
| `$call->request` (`McpToolCall`, `McpResourceRead`) | `$call->http` (`McpHttpRequest`) |
| `AlwaysAvailable::state()` | `AlwaysAvailable::availabilityOf()` |

Steps:

1. In your authenticator, implement `McpRequestAuthenticatorInterface`, rename `authenticate()` to
   `authenticateRequest()`, type the argument `McpHttpRequest` and replace `McpCredential::fromRequest($request)` with
   `$request->credential()`. Do the same for the availability hook (`availabilityOf()`). The `hooks.*` service ids do
   not change.
2. In tools and resource templates, replace `$call->request` with `$call->http` (method, path, headers, client IP).
3. A class may implement both ports during the migration; the bundle then calls the `McpHttpRequest` method only.
4. Do not log credential headers yourself: `McpHttpRequest` redacts them in every loggable form, but `header()` and
   `credential()` return the raw values.

## Adopting 1.0.0

First release. To serve MCP from an application:

1. `composer require nowo-tech/mcp-kit-bundle` (Flex adds `config/packages/nowo_mcp_kit.yaml` and the route import;
   otherwise import `@NowoMcpKitBundle/config/routes/mcp.yaml` outside any locale prefix).
2. Add a **stateless** firewall on the endpoint whose authenticator turns a valid bearer token into the security user
   and lets a bad token through with no user. No `access_control` rule on the path.
3. Implement the hooks and set `hooks.*`: `McpRequestAuthenticatorInterface` (required; 1.0:
   `McpAuthenticatorInterface`), `McpToolAuthorizerInterface`, `McpRateLimiterInterface` (the default refuses
   everything), optionally `McpRequestAvailabilityInterface` (1.0: `McpAvailabilityInterface`).
4. Register tools (`McpToolInterface`) and resource templates (`McpResourceTemplateInterface`); put text written by
   people under `untrusted`.
5. Listen to `McpToolCallEvent` (`McpKitEvents::TOOL_CALL`): additive and destructive tools are refused without a
   listener.
6. Set `enabled: true` and `server_info`; keep `allowed_origins` empty unless a browser client needs it.
7. Follow the host checklist in [SECURITY.md](SECURITY.md#host-checklist): proxy body cap, `trusted_proxies`, no
   early body readers, no logging of the endpoint's bodies.

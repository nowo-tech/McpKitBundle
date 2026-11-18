# Upgrading

## Table of contents

- [Upgrading to the next minor (write units)](#upgrading-to-the-next-minor-write-units)
- [Upgrading to 1.1.0](#upgrading-to-110)
- [Adopting 1.0.0](#adopting-100)

## Upgrading to the next minor (write units)

No action is required: `write_unit.enabled` is off by default and nothing changes without it.

To run write tools as write units (recommended with nowo-tech/transaction-doctrine):

1. `composer require nowo-tech/transaction-doctrine:^1.3` and enable its bundle.
2. Set `nowo_mcp_kit.write_unit.enabled: true`.
3. Remove the MCP route from transaction-doctrine's `controller_routes` if you listed it there: the invoker now replays
   the tool itself, and a route-level unit would never see the conflict.
4. Check that each additive or destructive tool is replayable: it loads what it changes by id, commits at most once and
   sends side effects after its commit. `McpToolCall::$attempt` tells a tool which attempt it is on.
5. Make `McpToolCallEvent` listeners refer to the principal by id (`tokenId`, the user's identifier) or rely on the
   refreshed principal: after a replay or a conflict the entity managers were cleared, and the invoker refreshes the
   principal through the firewall's user provider before the event. If `$user` or `$hostContext` is not reloadable
   that way, set `hooks.principal_refresher` (`McpPrincipalRefresherInterface`). The event has new `attempts` and
   `replayed` properties.
6. If you `match` exhaustively on `McpCallOutcome`, add the new `Conflict` case (`conflict`). Clients may now receive
   the JSON-RPC error `-31007` (`McpProtocol::WRITE_CONFLICT`, `data.code` `CONFLICT_001`, retryable) or the error
   result `CONFLICT_002` (part of the write saved; do not repeat).
7. Authenticators and rate limiters that use Doctrine should flush or clear: with a transaction open or unsaved changes
   the unit runs only once.

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

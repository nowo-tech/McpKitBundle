# Configuration

All options with their defaults. Everything is opt-in: with `enabled: false` the bundle registers nothing reachable
(the route import loads an empty collection).

```yaml
nowo_mcp_kit:
    enabled: false
    path: /mcp                  # absolute, outside any locale prefix
    server_info:                # serverInfo: configuration only (container parameters allowed), never request data
        name: app
        title: null
        version: '0.0.0'
    allowed_origins: []         # scheme://host[:port]; empty: any request carrying Origin gets 403
    legacy:
        enabled: true           # serve initialize-based clients statelessly
        versions: ['2025-11-25', '2025-06-18']
    cache:                      # ttlMs hints; cacheScope is always "private"
        discover_ttl_ms: 300000
        tools_list_ttl_ms: 300000
        templates_list_ttl_ms: 300000
        resources_list_ttl_ms: 300000
    limits:
        max_body_bytes: 1048576 # 413 above (Content-Length and streamed read)
        max_json_depth: 64      # 400 -32700 above
        max_text_chars: 100000  # default page of McpTextSlice
        max_list_rows: 50       # default page of list tools
        max_result_bytes: 2097152 # serialized tool result cap (OUTPUT_001)
        slow_call_ms: 2000      # slower calls are logged at warning level
    maintenance_retry_after: 60 # Retry-After of the maintenance 503
    firewall_check: true        # 500 unless the firewall user is the principal's user behind a secured, stateless firewall
                                # false: tests only, refused unless kernel.debug is true
    hooks:
        authenticator: ~        # McpAuthenticatorInterface service id: required when enabled
        authorizer: ~           # McpToolAuthorizerInterface: default allows no tool
        rate_limiter: ~         # McpRateLimiterInterface: default refuses every request (429)
        availability: ~         # McpAvailabilityInterface: default always available
```

## Table of contents

- [Route](#route)
- [Hooks](#hooks)
- [Firewall](#firewall)
- [Versions](#versions)

## Route

```yaml
# config/routes/nowo_mcp_kit.yaml
nowo_mcp_kit:
    resource: '@NowoMcpKitBundle/config/routes/mcp.yaml'
```

The route accepts every method: the endpoint itself answers 405 to anything but POST. Import it outside any locale
prefix.

## Hooks

| `hooks.*` | Interface | Without it |
| --- | --- | --- |
| `authenticator` | `Security\McpAuthenticatorInterface`: a `McpPrincipal` per request, or `McpAuthFailure::Invalid` (401) / `Disabled` (404) | The container does not build |
| `authorizer` | `Security\McpToolAuthorizerInterface`: which tools a principal may list and call (levels and ticks only) | No tool listed; every call `TOKEN_001` |
| `rate_limiter` | `Security\McpRateLimiterInterface`: token budget, write budget, failed tokens per IP | Every request 429 |
| `availability` | `Security\McpAvailabilityInterface`: `Available`, `Disabled` (404) or `Maintenance` (503) | Always available |

## Firewall

The endpoint must sit behind a **stateless, secured firewall** whose security user is the token's user: tools read
access through the security token. A bad token should pass through with no user, so the bundle answers its own 401.
No `access_control` rule on the path.

```yaml
security:
    firewalls:
        mcp:
            pattern: ^/mcp$
            stateless: true
            custom_authenticators: [App\Security\BearerTokenAuthenticator]
```

With `firewall_check: true` (default), a principal whose user is not the firewall user, or a request that did not go
through a secured **stateless** firewall, answers 500 and logs one error. A stateful firewall would store the
bearer-authenticated token in a session and turn an agent token into a full web session. `firewall_check: false` is for
tests only: the container refuses it unless `kernel.debug` is true.

## Versions

Modern `2026-07-28` is always served. `legacy.versions` is a non-empty subset of `2025-11-25` and `2025-06-18`, newest
first; an `initialize` asking for another version gets the first one.

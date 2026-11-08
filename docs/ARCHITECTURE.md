# Architecture

| Layer | Classes |
| --- | --- |
| Bundle and DI | `NowoMcpKitBundle`, `DependencyInjection\Configuration`, `DependencyInjection\NowoMcpKitExtension` |
| Routing | `Routing\McpRouteLoader` (`type: nowo_mcp_kit`), `config/routes/mcp.yaml` |
| HTTP | `Http\McpEndpointController` (the pipeline), `Http\McpMediaType`, `Http\McpHeaderValidator`, `Http\McpEndpointOptions` |
| Protocol | `Protocol\McpProtocol` (versions, `_meta` keys, codes, notice), `Protocol\McpEra`, `Protocol\JsonRpcError` |
| Server | `Server\McpDispatcher` (methods), `Server\McpRequest`, `Server\McpServerInfo` |
| Security hooks | `Security\McpAuthenticatorInterface`, `McpPrincipal`, `McpAuthFailure`, `McpCredential`, `McpRateLimiterInterface`, `McpRateLimitState`, `DenyingMcpRateLimiter`, `McpAvailabilityInterface`, `McpAvailability`, `AlwaysAvailable`, `McpFirewallGuard` |
| Context | `Context\McpCallContext`, `Context\McpClientInfo`, `Context\McpCallContextProvider` |

Per-request data (principal, rate-limit state, call context) lives on the `Request` attributes; services are readonly.
The bundle makes no outbound request.

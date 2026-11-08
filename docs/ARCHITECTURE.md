# Architecture

| Layer | Classes |
| --- | --- |
| Bundle and DI | `NowoMcpKitBundle`, `DependencyInjection\Configuration`, `DependencyInjection\NowoMcpKitExtension` |
| Routing | `Routing\McpRouteLoader` (`type: nowo_mcp_kit`), `config/routes/mcp.yaml` |
| HTTP | `Http\McpEndpointController` (the pipeline), `Http\McpMediaType`, `Http\McpHeaderValidator`, `Http\McpEndpointOptions` |
| Protocol | `Protocol\McpProtocol` (versions, `_meta` keys, codes, notice), `Protocol\McpEra`, `Protocol\JsonRpcError` |
| Server | `Server\McpDispatcher` (methods), `Server\McpRequest`, `Server\McpServerInfo` |
| Security hooks | `Security\McpAuthenticatorInterface`, `McpPrincipal`, `McpAuthFailure`, `McpCredential`, `McpRateLimiterInterface`, `McpRateLimitState`, `DenyingMcpRateLimiter`, `McpAvailabilityInterface`, `McpAvailability`, `AlwaysAvailable`, `McpFirewallGuard` |
| Tools | `Tool\McpToolInterface`, `McpToolDefinition`, `McpToolKind`, `McpToolCall`, `McpToolResult`, `McpToolError`, `McpUntrusted`, `McpResourceLink`, `McpIdempotencyKey` |
| Registry | `DependencyInjection\Compiler\McpRegistryPass` (build-time), `Registry\McpToolRegistry`, `Registry\McpResourceTemplateRegistry`, `Server\McpToolInvoker`, `Server\McpResourceReader` |
| Resources | `Tool\McpResourceTemplateInterface`, `McpResourceTemplateDefinition`, `McpResourceRead` |
| Paging | `Paging\McpTextSlice`, `Paging\McpCursor`, `Paging\McpLimits` |
| Schemas | `Schema\McpSchemaRules` (build-time rules), `Schema\McpSchemaNormalizer`, `Schema\McpSchemaValidator` (opis/json-schema, no remote loader) |
| Envelope | `Envelope\McpEnvelope` (untrusted groups, escaped text block) |
| Authorization | `Security\McpToolAuthorizerInterface`, `DenyingMcpToolAuthorizer` |
| Audit | `Event\McpToolCallEvent`, `Event\McpCallOutcome` |
| Context | `Context\McpCallContext`, `Context\McpClientInfo`, `Context\McpCallContextProvider` |

Per-request data (principal, rate-limit state, call context) lives on the `Request` attributes; services are readonly.
The bundle makes no outbound request.

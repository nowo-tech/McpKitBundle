# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/mcp-kit-bundle`  
**Last audited**: 2026-11-08

Scope: every file under `src/` plus the shipped route imports under `config/routes/`.

## Bundle and dependency injection

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `src/NowoMcpKitBundle.php` | Bundle and DI | FR-BUNDLE-001 |
| `src/DependencyInjection/Configuration.php` | Bundle and DI | FR-CFG-001 |
| `src/DependencyInjection/NowoMcpKitExtension.php` | Bundle and DI | FR-CFG-001 |

## Routes

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `config/routes/mcp.yaml` | Bundle and DI | FR-ROUTE-001 |
| `src/Routing/McpRouteLoader.php` | Bundle and DI | FR-ROUTE-001 |

## Transport (HTTP)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `src/Http/McpEndpointController.php` | Transport | FR-HTTP-001..005, FR-PROTO-001, FR-PROTO-002, FR-SEC-001..004 |
| `src/Http/McpMediaType.php` | Transport | FR-HTTP-003 |
| `src/Http/McpHeaderValidator.php` | Protocol | FR-PROTO-001, FR-PROTO-003 |
| `src/Http/McpEndpointOptions.php` | Transport | FR-CFG-002, FR-PROTO-002 |

## Protocol and server

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `src/Protocol/McpProtocol.php` | Protocol | FR-PROTO-001..004 |
| `src/Protocol/McpEra.php` | Protocol | FR-PROTO-001, FR-PROTO-002 |
| `src/Protocol/JsonRpcError.php` | Protocol | FR-HTTP-002, FR-PROTO-004 |
| `src/Server/McpDispatcher.php` | Protocol | FR-PROTO-004 |
| `src/Server/McpRequest.php` | Protocol | FR-PROTO-004 |
| `src/Server/McpServerInfo.php` | Protocol | FR-CFG-002, FR-PROTO-004 |

## Security hooks and context

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `src/Security/McpAuthenticatorInterface.php` | Security hooks | FR-SEC-001 |
| `src/Security/McpAuthFailure.php` | Security hooks | FR-SEC-001 |
| `src/Security/McpPrincipal.php` | Security hooks | FR-SEC-001 |
| `src/Security/McpCredential.php` | Security hooks | FR-SEC-001 |
| `src/Security/McpFirewallGuard.php` | Security hooks | FR-SEC-002 |
| `src/Security/McpRateLimiterInterface.php` | Security hooks | FR-SEC-003 |
| `src/Security/McpRateLimitState.php` | Security hooks | FR-SEC-003 |
| `src/Security/DenyingMcpRateLimiter.php` | Security hooks | FR-SEC-003 |
| `src/Security/McpAvailabilityInterface.php` | Security hooks | FR-SEC-004 |
| `src/Security/McpAvailability.php` | Security hooks | FR-SEC-004 |
| `src/Security/AlwaysAvailable.php` | Security hooks | FR-SEC-004 |
| `src/Context/McpCallContext.php` | Security hooks | FR-CTX-001 |
| `src/Context/McpClientInfo.php` | Security hooks | FR-CTX-001 |
| `src/Context/McpCallContextProvider.php` | Security hooks | FR-CTX-001 |

## Tools, registry and envelope

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `src/Tool/McpToolInterface.php` | Tools | FR-TOOL-001 |
| `src/Tool/McpToolDefinition.php` | Tools | FR-TOOL-001, FR-TOOL-002 |
| `src/Tool/McpToolKind.php` | Tools | FR-TOOL-002, FR-TOOL-003 |
| `src/Tool/McpToolCall.php` | Tools | FR-TOOL-003 |
| `src/Tool/McpToolResult.php` | Tools | FR-TOOL-003, FR-AUDIT-001 |
| `src/Tool/McpToolError.php` | Tools | FR-TOOL-004 |
| `src/Tool/McpUntrusted.php` | Tools | FR-ENV-001 |
| `src/Tool/McpResourceLink.php` | Tools | FR-TOOL-003 |
| `src/Tool/McpIdempotencyKey.php` | Tools | FR-TOOL-003 |
| `src/Security/McpToolAuthorizerInterface.php` | Tools | FR-TOOL-002 |
| `src/Security/DenyingMcpToolAuthorizer.php` | Tools | FR-TOOL-002 |
| `src/DependencyInjection/Compiler/McpRegistryPass.php` | Tools | FR-TOOL-001 |
| `src/Registry/McpToolRegistry.php` | Tools | FR-TOOL-001, FR-TOOL-002 |
| `src/Schema/McpSchemaRules.php` | Tools | FR-TOOL-001 |
| `src/Schema/McpSchemaNormalizer.php` | Tools | FR-TOOL-002 |
| `src/Schema/McpSchemaValidator.php` | Tools | FR-TOOL-003 |
| `src/Server/McpToolInvoker.php` | Tools | FR-TOOL-002..004, FR-AUDIT-001 |
| `src/Envelope/McpEnvelope.php` | Tools | FR-ENV-001 |
| `src/Event/McpToolCallEvent.php` | Tools | FR-AUDIT-001 |
| `src/Event/McpCallOutcome.php` | Tools | FR-AUDIT-001 |
| `src/Event/McpKitEvents.php` | Tools | FR-AUDIT-001 |

## Resources and paging

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `src/Tool/McpResourceTemplateInterface.php` | Resources | FR-RES-001 |
| `src/Tool/McpResourceTemplateDefinition.php` | Resources | FR-RES-001, FR-RES-002 |
| `src/Tool/McpResourceRead.php` | Resources | FR-RES-002 |
| `src/Registry/McpResourceTemplateRegistry.php` | Resources | FR-RES-001 |
| `src/Server/McpResourceReader.php` | Resources | FR-RES-001, FR-RES-002 |
| `src/Paging/McpTextSlice.php` | Paging | FR-PAGE-001 |
| `src/Paging/McpCursor.php` | Paging | FR-PAGE-001 |
| `src/Paging/McpLimits.php` | Paging | FR-PAGE-001 |

# Feature Specification: McpKitBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-11-08  
**Status**: Active  

**Package**: `nowo-tech/mcp-kit-bundle`  
**Configuration root**: `nowo_mcp_kit`  
**Code inventory**: [`code-inventory.md`](code-inventory.md)  
**Integrator spec**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md)  
**Source contract**: Beacon spec 219 (MCP server), contract v1.0 (decisions of 2026-11-08)

---

## Summary

A Model Context Protocol server endpoint for Symfony hosts. The bundle supplies the transport (stateless Streamable
HTTP, JSON only), protocol versions (modern 2026-07-28, legacy 2025-11-25 and 2025-06-18), the order of refusals,
host hooks for authentication, authorization, budgets and availability, a compiled tool registry with JSON Schema
validation, the untrusted envelope, size caps and an audit event. The host registers the tools.

## Requirements

### Bundle and DI

- **FR-BUNDLE-001** The bundle path is the package root; the extension alias is `nowo_mcp_kit`.
- **FR-CFG-001** `enabled` (default false) and `path` (default `/mcp`, absolute) configure the endpoint; with
  `enabled: false` nothing reachable is registered.

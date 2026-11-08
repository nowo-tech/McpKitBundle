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

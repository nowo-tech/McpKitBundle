# McpKitBundle Constitution

## Core Principles

### I. Documented integrator contract
Product behavior lives in `specs/001-baseline/spec.md`, `docs/SPEC-DRIVEN-DEVELOPMENT.md`, and integrator docs (`USAGE.md`, `CONFIGURATION.md`). Demos are illustrative unless promoted in the spec.

### II. Spec-first, test-proven
PHPUnit and PHPStan are the mechanical proof. Behavioral changes require tests. Protocol behaviour is also proven by the official MCP conformance runner.

### III. 100% code inventory traceability
Every production file under `src/` must appear in `specs/001-baseline/code-inventory.md`. New files require spec updates in the same PR.

### IV. Cursor + Spec Kit
GitHub Spec Kit is initialized with **Cursor Agent** (`cursor-agent`). Skills live in `.cursor/skills/speckit-*`.

### V. Symfony compatibility
Follow declared PHP/Symfony ranges in `composer.json` and README badges.

### VI. Fail closed, domain-free
The bundle knows no domain: the host authenticates, authorizes and registers tools. Anything the bundle cannot establish is a refusal. Credentials are read from the `Authorization` header only and never logged or echoed.

### VII. Data is not instructions
Protocol strings (instructions, tool descriptions, titles, error messages, server info) come from code and configuration only. Content written by people is returned under `untrusted`, JSON-escaped, behind a fixed notice.

### VIII. Stateless and worker-safe
No sessions, no state handles, nothing kept between requests. Services are readonly; per-request data lives on the request.

## Governance
Amendments update this file, baseline spec when principles affect behavior, and `CHANGELOG.md` when consumer-visible.

**Version**: 1.0.0 | **Ratified**: 2026-11-08 | **Last Amended**: 2026-11-08

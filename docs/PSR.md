# PHP-FIG PSR evaluation (REQ-CS-007)

Package: `nowo-tech/mcp-kit-bundle` (`symfony-bundle`)

This document records which [PHP-FIG PSRs](https://www.php-fig.org/psr/) apply to this package.
Only contracts that add clear interoperability or maintainability value are **Adopted**.
Others are **N/A** (or already covered by Symfony) so the decision stays auditable.

## Table of contents

- [Baseline (always)](#baseline-always)
- [Interface / contract PSRs](#interface--contract-psrs)
- [Summary](#summary)

## Baseline (always)

| PSR | Decision | How |
| --- | -------- | --- |
| PSR-12 (coding style) | **Adopted** | `@PSR12` in `.php-cs-fixer.dist.php` (Nowo REQ-CS-001). |
| PSR-4 (autoloading) | **Adopted** | `composer.json` `autoload` / `autoload-dev` PSR-4 map for package sources and tests. |

## Interface / contract PSRs

| PSR | Decision | Notes |
| --- | -------- | ----- |
| PSR-3 Logger | **Adopted** | The endpoint and dispatcher take an optional `Psr\Log\LoggerInterface`. Logs carry method, tool, outcome, duration and exception class only. |
| PSR-6 / PSR-16 Cache | **N/A** | No package-owned cache; the tool registry is compiled into the container. |
| PSR-7 / PSR-17 HTTP messages | **N/A** | Symfony HttpFoundation is the surface; a PSR-7 bridge would not help consumers. |
| PSR-18 HTTP client | **N/A** | The bundle makes no outbound request (no SSRF surface). |
| PSR-11 Container | **Internal** | Tool instances are fetched from a Symfony service locator built by the registry pass; not part of the public API. |
| PSR-14 Event dispatcher | **Adopted (Symfony)** | `McpToolCallEvent` is dispatched through Symfony's `EventDispatcherInterface` (the bundle also needs `hasListeners()`). |
| PSR-15 HTTP middleware | **N/A** | One Symfony controller runs the pipeline. |
| PSR-20 Clock | **Adopted** | `Retry-After` and durations use `Psr\Clock\ClockInterface` (framework `clock`, else `NativeClock`); tests use `MockClock`. |

## Summary

- **Adopted beyond baseline:** PSR-3 (logger) and PSR-20 (clock), both type-hinted and wired.
- **Rule:** do not add `psr/*` Composer dependencies without matching type-hints and DI wiring.

---

_REQ-CS-007 evaluation date: 2026-11-08._

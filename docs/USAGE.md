# Usage

## Table of contents

- [Endpoint](#endpoint)
- [Order of checks](#order-of-checks)
- [Methods](#methods)
- [Hooks](#hooks)
- [Call context](#call-context)
- [Connecting a client](#connecting-a-client)

## Endpoint

One route (`nowo_mcp_kit.path`, default `/mcp`). Every JSON-RPC message is its own `POST`; answers are always
`application/json`. No session (`Mcp-Session-Id` is never issued and is ignored), no SSE, no GET stream (405).

## Order of checks

The first failure answers. Errors before the body is parsed carry no `id`.

| # | Check | Answer | JSON-RPC code |
| --- | --- | --- | --- |
| 1 | `availability` says disabled | 404, empty | — |
| 2 | `Origin` present and not allowed | 403 | `-31003` |
| 3 | Method is not POST | 405 `Allow: POST`, empty | — |
| 4 | Maintenance | 503 + `Retry-After` | `-31005` |
| 5 | `Content-Length` above `max_body_bytes` | 413 | `-31004` |
| 6 | Failed tokens from this IP | 429 + `Retry-After` | `-31001` `data.limit: failed_tokens` |
| 7 | No `Authorization` credential | 401 `WWW-Authenticate: Bearer` | `-31002` |
| 8 | Authenticator: `Invalid` (counted as a failure) / `Disabled` | 401 / 404 empty | `-31002` / — |
| 9 | Firewall user is not the principal's user | 500 | `-32603` |
| 10 | Token budget | 429 + `Retry-After` | `-31001` `data.limit: requests` |
| 11 | `Content-Type` not `application/json` (UTF-8) | 415 | `-31006` |
| 12 | `Accept` excludes JSON | 406 | `-31006` |
| 13 | Body above the cap (also chunked) | 413 | `-31004` |
| 14 | Invalid JSON, invalid UTF-8, too deep | 400 | `-32700` |
| 15 | Not a single request or notification; bad `id`; params not an object | 400 | `-32600` / `-32602` |
| 16 | Notification | 202, empty | — |
| 17 | Era and version | 400 | `-32022` / `-32602` / `-32020` |
| 18 | `Mcp-Method` / `Mcp-Name` headers | 400 | `-32020` |
| 19 | Unknown method | modern 404, legacy 200 | `-32601` |

Every answer carries `Cache-Control: no-store, private` and `X-Content-Type-Options: nosniff`; after step 10 also
`X-RateLimit-Limit`, `-Remaining` and `-Reset`.

## Methods

| Method | Modern (`2026-07-28`) | Legacy (`2025-11-25`, `2025-06-18`) |
| --- | --- | --- |
| `server/discover` | versions, capabilities (`tools`, `resources`), `instructions`, `serverInfo` in `_meta`, `ttlMs`, `cacheScope: private` | `-32601` |
| `initialize` | 404 `-32601` | negotiates a legacy version, no session |
| `ping` | 404 `-32601` | `{}` |
| `tools/list`, `tools/call` | see the tool registry | same, without `resultType`, `ttlMs`, `cacheScope`, `_meta` |
| `resources/list` | always empty | same |
| `resources/templates/list`, `resources/read` | see the resource templates | same |
| anything else | 404 `-32601` | 200 `-32601` |

`instructions` is the fixed untrusted notice and nothing else.

## Hooks

```php
use Nowo\McpKitBundle\Security\McpAuthenticatorInterface;
use Nowo\McpKitBundle\Security\McpAuthFailure;
use Nowo\McpKitBundle\Security\McpCredential;
use Nowo\McpKitBundle\Security\McpPrincipal;

final readonly class TokenAuthenticator implements McpAuthenticatorInterface
{
    public function authenticate(Request $request): McpPrincipal|McpAuthFailure
    {
        $token = $this->tokens->findValid(McpCredential::fromRequest($request)); // revoked/expired → null
        if ($token === null) {
            return McpAuthFailure::Invalid;
        }

        return new McpPrincipal($token->getUser(), $token->getId(), $token->getName(), $token->agentsMayDelete(), $token->levels());
    }
}
```

The principal is built per request and never kept. `$tokenId` is an opaque id, never the secret.

## Call context

`Context\McpCallContext::fromRequest($request)` (or the `McpCallContextProvider` service) gives the era, the protocol
version, the client as reported (`clientInfo`, sanitised, ≤ 64 characters) and the cleaned `User-Agent` product token.
Self-reported values are for display ("reported as"), never for decisions.

## Connecting a client

```bash
claude mcp add --transport http my-app https://app.example/mcp --header "Authorization: Bearer ${MY_APP_TOKEN}"
```

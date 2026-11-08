# MCP Kit Bundle Demo (Symfony 8)

FrankenPHP (worker mode) demo of `nowo-tech/mcp-kit-bundle`. No database: demo data lives in the container's
filesystem cache.

## Usage

```bash
make up                # starts FrankenPHP (worker mode) on http://localhost:8219
make test              # PHPUnit (functional tests of the MCP endpoint)
make test-conformance  # official MCP conformance runner, both protocol revisions
make down
```

Open `http://localhost:8219` for the client setup (tokens, `claude mcp add`, `curl` examples).

## Included dev stack

- Symfony Web Profiler and debug mode (`APP_DEBUG=1` in `.env.example`)
- `nowo-tech/twig-inspector-bundle`, `nowo-tech/hot-reload-bundle`

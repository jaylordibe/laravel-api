# MCP endpoint

The API exposes a [Model Context Protocol](https://modelcontextprotocol.io)
server at **`/api/mcp`** so MCP clients (n8n, Claude Code, MCP Inspector, other
agents) can connect to it. Today it serves only three connectivity tools; it is
the foundation domain tools are added to later.

## 1. Purpose and architecture

- **Package:** the official [`laravel/mcp`](https://laravel.com/docs/13.x/mcp)
  (`^1.1`), a production dependency.
- **Where:** `routes/ai.php` (route, loaded by laravel/mcp), `app/Mcp/Servers/ApiServer.php`
  (server, tool list, identity), `app/Mcp/Tools/*Tool.php` (one class per tool),
  `app/Http/Middleware/AuthenticateMcpClient.php` (authentication),
  `app/Utils/McpClientCredentialUtil.php` (credential parsing and matching).
  It runs in the API process; there is no separate service or gateway.
- **Transport:** Streamable HTTP, **stateless**. The server is built per HTTP
  request and keeps no session, so any replica can answer any request.
- **Protocol versions:** 2026-07-28 (`server/discover`, per-request `_meta`)
  and 2025-era clients (`initialize` with 2025-11-25, 2025-06-18 or
  2025-03-26; a client asking for anything older is offered 2025-11-25) on the
  same endpoint. `GET` and `DELETE` answer `405` after authentication.
- **Error status:** 2026-07-28 requests keep laravel/mcp's statuses: `404`
  for an unknown method and `400` for header, version and `_meta` errors
  (both mandated by that revision), and `400`/`500` for other invalid-params
  and internal errors (laravel/mcp's choice, which the spec permits).
  2025-era method errors (`-32601`, `-32602`, `-32603`) are answered with
  HTTP `200` by `NormalizeLegacyMcpErrorStatus`, because 2025-era clients
  (the TypeScript SDK v1 that n8n uses) treat any non-2xx as a transport
  failure and never read the JSON-RPC error. Protocol-version and header
  errors keep their `400`.
- **Request order:** `throttle:mcp` (per IP) → `AuthenticateMcpClient`
  (enabled? Origin? token?) → `NormalizeLegacyMcpErrorStatus` →
  laravel/mcp's own header middleware → server.
  No MCP message is parsed before authentication passes.
- **Coexistence:** the route is not under `auth:api`. Passport user tokens do
  not work on `/api/mcp`, and MCP tokens do not work on any REST route.
  `AccessControlFeatureTest` counts `AuthenticateMcpClient` as authentication,
  so the route does not appear on the public-route list.

## 2. Installation and configuration

`composer install` installs the package. The endpoint is **off (404) until a
client token is configured**.

Create one token per client and configure its digest:

```bash
php -r '$t = bin2hex(random_bytes(32)); echo "token : $t\nsha256: " . hash("sha256", $t) . "\n";'
```

```dotenv
MCP_CLIENT_TOKENS=n8n:<sha256-of-n8n-token>,claude-code:<sha256-of-claude-token>
```

Then reload configuration (`php artisan config:clear`, or restart the
container; deployed images cache config at start). Store the raw token in the
client's own secret store; the API never stores it.

## 3. Environment variables

| Variable | Default | Meaning |
|---|---|---|
| `MCP_CLIENT_TOKENS` | empty (off) | Comma-separated `name:sha256hex`. Name: lowercase slug (`[a-z0-9][a-z0-9._-]{0,63}`). Digest: 64 lowercase hex chars. A malformed value keeps the endpoint off **and** fails `app:check-config`, so the container refuses to start. |
| `MCP_ALLOWED_ORIGINS` | empty | Exact browser origins (`https://host[:port]`). Empty refuses every request carrying an `Origin` header. |
| `RATE_LIMIT_MCP` | `60` | Requests per minute per client IP. The default is the production floor. |

These are read in `config/custom.php` (`custom.mcp.*`, `custom.rate_limits.mcp`).

## 4. Authentication

- `Authorization: Bearer <token>`, one token per client. Only SHA-256 digests
  are configured, compared with `hash_equals`. SHA-256 rather than a slow hash
  is deliberate: the tokens are 256-bit random values, not passwords.
- Failures: no or unknown token → `401` with `WWW-Authenticate: Bearer
  realm="mcp", error="invalid_token"` (laravel/mcp's global
  `AddWwwAuthenticateHeader` sets that value on every 401 from this route,
  including a missing token); a browser
  `Origin` not allowed → `403`, checked before the token; no client configured
  → `404`. Bodies use the API's usual `{"message": …}` envelope.
- **Revoke** a client: remove its entry and reload config. **Rotate**: add the
  new digest under a new name, switch the client, then remove the old entry.
- The authenticated client's name is the request attribute `mcp_client`
  (`request()->attributes->get('mcp_client')`).

**Which clients this supports:** any client that can send a static header —
n8n, Claude Code, MCP Inspector, custom SDK agents. **Which may need OAuth
later:** ChatGPT connectors and claude.ai custom connectors typically expect
the MCP OAuth 2.1 flow. This application already runs Passport, and laravel/mcp
supports it (`Mcp::oauthRoutes()` plus `auth:api` on the MCP route). That is
the documented path when one of those clients is needed, and the tools do not
change. It is not enabled today, because it exposes public dynamic
client-registration routes and ties MCP access to user accounts.

## 5. Endpoint URL

| Environment | URL |
|---|---|
| Local (Docker) | `http://localhost:8000/api/mcp` (`APP_PORT`) |
| Deployed | `https://<api-host>/api/mcp` — HTTPS only |

## 6. Available tools

All three are read-only, idempotent and deterministic, and touch no database,
model or service. `tools/list` returns them in this order.

| Tool | Input | Output (`structuredContent`) |
|---|---|---|
| `ping` | none | `{ "success": true, "message": "pong" }` |
| `echo` | `{ "message": string }` — 1–1000 chars, not only whitespace; other keys are ignored | `{ "success": true, "message": <same string> }` |
| `get_server_info` | none | `{ "name": <APP_NAME>, "mcp": true, "status": "ready", "version": ApiServer::VERSION }` |

The application has no version of its own (composer.json declares none), so
`ApiServer::VERSION` is the single stated version. Invalid `echo` arguments
come back as a tool result with `isError: true` and an actionable message. An
unknown tool returns JSON-RPC error `-32602`.

## 7. Local testing

```bash
./test.sh McpFeatureTest tests/Feature/McpFeatureTest.php
docker exec -it laravel-api bash -c "php artisan test --compact tests/Unit/McpClientCredentialUtilTest.php"
```

`McpFeatureTest` drives the full HTTP stack with real JSON-RPC in both protocol
eras: handshake, discovery, all three tools, `echo` validation, unknown tools
and methods, malformed JSON, every authentication failure, Passport tokens
being refused, the Origin check, and the disabled state. The rate limiter is
not exercised, because `Tests\TestCase` disables throttling for the whole suite.

## 8. MCP Inspector

```bash
npx @modelcontextprotocol/inspector --cli http://localhost:8000/api/mcp \
  --transport http --header "Authorization: Bearer $MCP_TOKEN" --method tools/list

npx @modelcontextprotocol/inspector --cli http://localhost:8000/api/mcp \
  --transport http --header "Authorization: Bearer $MCP_TOKEN" \
  --method tools/call --tool-name echo --tool-arg message="Hello MCP"
```

UI mode: `npx @modelcontextprotocol/inspector`, choose **Streamable HTTP**,
URL `http://localhost:8000/api/mcp`, header `Authorization: Bearer <token>`. If
the UI's requests carry its own origin, add it (for example
`http://localhost:6274`) to `MCP_ALLOWED_ORIGINS` locally.

## 9. n8n

Use the **MCP Client Tool** node attached to an AI Agent:

- **Endpoint:** `https://<api-host>/api/mcp`
- **Server Transport:** HTTP Streamable. Older n8n versions offer only the
  deprecated HTTP+SSE transport, which this endpoint does not serve.
- **Authentication:** Bearer Auth → a credential holding the `n8n` client's
  token (or Header Auth: `Authorization` / `Bearer <token>`).
- **Tools to Include:** All.

## 10. Claude Code

```bash
claude mcp add --transport http laravel-api http://localhost:8000/api/mcp \
  --header "Authorization: Bearer <claude-code-token>"
claude mcp list
```

Or a project `.mcp.json` entry, keeping the token out of the file:

```json
{
  "mcpServers": {
    "laravel-api": {
      "type": "http",
      "url": "https://<api-host>/api/mcp",
      "headers": { "Authorization": "Bearer ${LARAVEL_API_MCP_TOKEN}" }
    }
  }
}
```

The `laravel-boost` entry already in `.mcp.json` is a separate, local
development tool server (stdio); it is unrelated to this endpoint.

## 11. Adding a tool

1. `php artisan make:mcp-tool <Name>Tool --no-interaction`, then follow
   `EchoTool`: `#[Name]` (snake_case), `#[Description]`, honest annotations,
   `schema()`, `outputSchema()`, and validation in `handle()` with actionable
   messages. Run `php artisan app:format`.
2. Add the class to `ApiServer::$tools`. Its position is its `tools/list`
   order.
3. A tool that reads or writes data calls the existing Service layer. It never
   queries Eloquent directly, and it never takes a user or tenant id from its
   arguments as proof of access. Today every client can call every tool;
   decide the tool's authorization first (per client via `mcp_client`, or
   OAuth with Passport scopes).
4. Bump `ApiServer::VERSION` when the tool surface changes.
5. Cover it in `McpFeatureTest`: discovery, the happy path, validation.

## 12. Production considerations

- **HTTPS only.** Bearer tokens over plain HTTP are readable on the path.
- **Off by default.** An environment exposes MCP only after
  `MCP_CLIENT_TOKENS` is set there. Use different tokens per environment.
- **Edge:** the path is under `/api`, so existing proxy rules cover it. A WAF
  or CDN must allow `POST` JSON bodies and must not cache `/api/mcp`.
- **Errors:** `APP_DEBUG` must stay false. With debug on, laravel/mcp puts
  exception messages into tool error results. `app:check-config` already
  fails production on `APP_DEBUG=true`.
- **Logs:** `Authorization` is redacted by `App\Logging\RedactSensitiveData`.
  Request bodies are not logged.
- **Scale:** stateless. The `mcp` limiter counts in the cache store (Redis in
  production), shared across replicas.
- **Not configured here:** OAuth, per-tool authorization, per-client rate
  buckets, and audit logging of tool calls. Add these with the first tool
  that touches data.

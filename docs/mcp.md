# MCP endpoint

The API exposes a [Model Context Protocol](https://modelcontextprotocol.io)
server at **`/api/mcp`**. An MCP client (Claude Code, claude.ai, n8n, MCP
Inspector, other agents) connects with **only that URL**: it is sent to the
API's own sign-in page, the user signs in with their normal account and
approves the client, and the client then calls tools **as that user**. No
token is pasted and nothing is configured per client.

## 1. Purpose and architecture

- **Packages:** the official [`laravel/mcp`](https://laravel.com/docs/13.x/mcp)
  serves MCP and the OAuth discovery and client-registration endpoints;
  [Laravel Passport](https://laravel.com/docs/13.x/passport) is the
  authorization server (authorization code with PKCE, refresh tokens).
- **Where:**
  - `routes/ai.php` — the endpoint, discovery (`McpOAuthMetadataController`,
    URLs pinned to the issuer) and registration;
    `app/Mcp/Servers/ApiServer.php` (server, tool list, version) and
    `app/Mcp/Tools/*Tool.php` (one class per tool).
  - `routes/oauth.php` — the Passport routes the flow needs (authorize,
    approve, deny, token) and the sign-in page, `OAuthLoginController` with
    `resources/views/mcp/*.blade.php`.
  - `app/Http/Middleware/` — `EnsureMcpToken` (the endpoint),
    `RejectMcpToken` (REST), `ValidateMcpClientRegistration`,
    `PrepareMcpAuthorizationRequest`, `AddOAuthPageSecurityHeaders`,
    `EnsureFreshOAuthSignIn`, `EndOAuthSignInSession`;
    `app/Utils/McpConfigUtil.php` (configuration parsing and the redirect
    rule), `McpTokenUtil.php` (what counts as an MCP token) and
    `McpSignInUtil.php` (a sign-in cannot outlive a revocation);
    `app/Repositories/SingleUse*Repository.php` (codes and refresh tokens are
    single-use under concurrency).
  - It runs in the API process; there is no separate service or gateway.
- **Standard:** the [MCP authorization specification](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization)
  — OAuth 2.1 authorization code with PKCE (S256 only), exact redirect URI
  matching, protected resource metadata (RFC 9728), authorization server
  metadata (RFC 8414), dynamic client registration (RFC 7591), and sign-in in
  the user's own browser (RFC 8252).
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
- **Request order on `/api/mcp`:** `throttle:mcp` (per IP) → `EnsureMcpToken`
  (Origin? token? scope?) → `NormalizeLegacyMcpErrorStatus` → laravel/mcp's
  own header middleware → server. No MCP message is parsed before
  authentication passes.

## 2. Installation and configuration

Nothing to configure beyond what the template already needs: `composer
install`, the Passport keys `./start.sh fresh` creates, and `APP_URL` set to
the public URL. The issuer is `APP_URL`, pinned in `config/mcp.php` rather
than taken from the request.

## 3. Environment variables

All optional. Read in `config/custom.php` (`custom.mcp.*`,
`custom.rate_limits.*`) and checked by `app:check-config`, so a malformed
value stops the container from starting.

| Variable | Default | Meaning |
|---|---|---|
| `MCP_ALLOWED_ORIGINS` | empty | Exact browser origins (`https://host[:port]`) allowed to call `/api/mcp`. Empty refuses every request carrying an `Origin` header; server-side clients send none. |
| `MCP_OAUTH_ACCESS_TOKEN_TTL_MINUTES` | `60` | Lifetime of an access token (5–1440). |
| `MCP_OAUTH_REFRESH_TOKEN_TTL_DAYS` | `30` | Lifetime of a refresh token (1–90). Each renewal issues a new one with this lifetime. |
| `MCP_OAUTH_REDIRECT_DOMAINS` | empty | Comma-separated https origins a client may register as its redirect (`https://claude.ai,https://n8n.example.com`). Empty allows any https origin. Loopback (`http://localhost`, `127.0.0.1`, `[::1]`) is always allowed. |
| `RATE_LIMIT_MCP` | `60` | Requests per minute per IP to `/api/mcp`. |
| `RATE_LIMIT_OAUTH` | `30` | Requests per minute per IP to the OAuth endpoints and pages. The sign-in form uses the REST sign-in limits. |

## 4. Authentication

### The sign-in flow

1. The client calls `/api/mcp` with no token and gets `401` with
   `WWW-Authenticate: Bearer realm="mcp", resource_metadata="<APP_URL>/.well-known/oauth-protected-resource/api/mcp"`.
2. It reads the protected resource metadata, then the authorization server
   metadata at `/.well-known/oauth-authorization-server`.
3. It registers itself at `POST /oauth/register` (no Client ID is created by
   hand). Every client is public and proves possession with PKCE.
4. It opens the user's browser at `/oauth/authorize`. A browser that is not
   signed in is sent to `/oauth/login`, which applies REST sign-in's rules:
   email or username, the same generic answer for a wrong password and an
   unknown account, a verified email required, the `sign-in` rate limits.
5. The consent page shows the client's name (marked as chosen by the client)
   and the exact redirect URL it uses. **Allow** sends a code to the client;
   **Deny** sends `access_denied` and grants nothing.
6. The client exchanges the code at `POST /oauth/token` for an access token
   (`MCP_OAUTH_ACCESS_TOKEN_TTL_MINUTES`) and a refresh token that rotates on
   every use.

The password and consent are asked for on **every** authorization, and the
browser session the sign-in created ends as soon as the user decides, so a
browser left behind cannot mint a token later. A sign-in must be approved
within 10 minutes, and one overtaken by a revocation (below) is refused at
approval (`EnsureFreshOAuthSignIn`).

### Who may connect, and when access ends

Any account that can sign in to the REST API: not deleted, email verified.
Access ends **at the next request** after:

- sign-out everywhere (`POST /api/auth/sign-out-all`);
- a password change, an administrator password reset, a password reset by
  email, or a confirmed email change;
- the account being deleted;
- the access token expiring (the client then refreshes) or the refresh token
  expiring.

Each of these revokes the user's access and refresh tokens and any
authorization code not yet redeemed (`UserService::revokeAllUserTokens`), and
records the revocation so a sign-in still waiting on the consent page is
refused too; the guard also reloads the user on every request. The client is answered `401`
with the challenge from step 1 and starts again.

### Token rules

- An MCP token works **only** on `/api/mcp`. A token is an MCP token when it
  was issued to an authorization-code client (only this flow issues those) or
  carries `mcp:use` (`McpTokenUtil`): `RejectMcpToken` refuses it on every
  REST route behind `auth:api`, and `EnsureMcpToken` accepts nothing else.
  Every authorization asks for exactly `mcp:use`, whatever the client sent.
  REST sign-in issues personal access tokens, which are refused on `/api/mcp`.
  Passport cannot bind a token to a resource (RFC 8707), so the client and
  scope bind it; a `resource` naming anything other than `/api/mcp` is
  refused at authorization.
- Failures on `/api/mcp`: no, invalid or non-MCP token → `401`; a browser
  `Origin` not in `MCP_ALLOWED_ORIGINS` → `403`, before the token is checked.
  Bodies use the API's `{"message": …}` envelope. The OAuth endpoints answer
  in OAuth's own format (`{ "error", "error_description" }`).
- Passport stores token identifiers, not the tokens: access tokens are signed
  JWTs verified against the Passport keys, and refresh tokens and codes are
  encrypted with `APP_KEY`.

### Protections

| Surface | Control |
|---|---|
| Sign-in and consent pages | `X-Frame-Options: DENY` and CSP `frame-ancestors 'none'`; nothing else may load; client-supplied text is escaped (Blade `{{ }}`). |
| Forged form posts | The pages are in the `web` group: Laravel's CSRF token on every form, and Passport's per-request `auth_token` on approve and deny. |
| Password guessing | The sign-in post has REST sign-in's `sign-in` limiter (5 per minute per identifier and IP, 30 per IP). |
| Registration, discovery, authorize, token | `throttle:oauth` per IP. |
| `/api/mcp` | `throttle:mcp` per IP, counted before authentication. |
| Redirects | Loopback or https only (`ValidateMcpClientRegistration`); `MCP_OAUTH_REDIRECT_DOMAINS` narrows https hosts. Passport matches the redirect exactly, except that the port of a `127.0.0.1` / `[::1]` redirect may change; a `localhost` redirect must match its port. |
| PKCE | S256 only (`PrepareMcpAuthorizationRequest`); Passport alone would accept `plain`. |
| Code and refresh-token reuse | Single-use even under concurrency (`SingleUse*Repository`): the first redemption attempt claims a code, so one presented with a wrong verifier is spent too. |
| Spoofed `Host` | The discovery documents are built from the issuer, not the request. |

## 5. Endpoint URLs

| Path | Purpose |
|---|---|
| `/api/mcp` | The MCP endpoint |
| `/.well-known/oauth-protected-resource/api/mcp` (and `/.well-known/oauth-protected-resource`) | RFC 9728 protected resource metadata |
| `/.well-known/oauth-authorization-server` | RFC 8414 authorization server metadata (the issuer has no path, so there is no path-inserted variant) |
| `POST /oauth/register` | Dynamic client registration |
| `/oauth/authorize` | Authorization and the consent page (GET), approve (POST), deny (DELETE) |
| `POST /oauth/token` | Code exchange and refresh |
| `/oauth/login` | The sign-in page |

The OAuth and discovery paths are at the origin root, outside `/api`, because
that is where OAuth clients look for them. Locally the MCP URL is
`http://localhost:8000/api/mcp` (`APP_PORT`); deployed,
`https://<api-host>/api/mcp`.

## 6. Available tools

All are read-only and idempotent. `tools/list` returns them in this order.

| Tool | Input | Output (`structuredContent`) |
|---|---|---|
| `ping` | none | `{ "success": true, "message": "pong" }` |
| `echo` | `{ "message": string }` — 1–1000 chars, not only whitespace; other keys are ignored | `{ "success": true, "message": <same string> }` |
| `get_server_info` | none | `{ "name": <APP_NAME>, "mcp": true, "status": "ready", "version": ApiServer::VERSION }` |
| `get_current_user` | none | `{ "id": <user id>, "email": <email>, "roles": [<role names>] }` — the signed-in user |

The application has no version of its own (composer.json declares none), so
`ApiServer::VERSION` is the single stated version. Invalid `echo` arguments
come back as `isError: true`; an unknown tool returns JSON-RPC error `-32602`.

## 7. Local testing

```bash
./test.sh McpOAuthFeatureTest tests/Feature/McpOAuthFeatureTest.php
./test.sh McpFeatureTest tests/Feature/McpFeatureTest.php
docker exec -it laravel-api bash -c "php artisan test --compact tests/Unit/McpConfigUtilTest.php"
```

`McpOAuthFeatureTest` runs the whole flow through the HTTP stack with a
browser's session cookie (`Tests\SignsInMcpClients`): discovery,
registration, sign-in, consent, n8n's request shapes, every refusal, every way
access ends, token separation, and the page protections. `McpFeatureTest`
drives the tools in both protocol eras. Rate limits are not exercised:
`Tests\TestCase` disables throttling for the whole suite.

## 8. MCP Inspector

`npx @modelcontextprotocol/inspector`, transport **Streamable HTTP**, URL
`http://localhost:8000/api/mcp`, then start its OAuth flow from the
authentication settings: it registers itself and opens the sign-in page. The
UI calls from its own origin, so add it (for example `http://localhost:6274`)
to `MCP_ALLOWED_ORIGINS` locally.

## 9. n8n

Use the **MCP Client Tool** node attached to an AI Agent:

- **Endpoint:** `https://<api-host>/api/mcp`
- **Server Transport:** HTTP Streamable
- **Authentication:** MCP OAuth2 → create a credential, leave **Register
  Client Automatically** on, and **Connect**: n8n registers itself, opens the
  sign-in page, and stores the tokens.

n8n needs 1.119 or later for automatic registration. Its callback is
`https://<n8n-host>/rest/oauth2-credential/callback`, so the n8n host must be
https (or allowed by `MCP_OAUTH_REDIRECT_DOMAINS`).

## 10. Claude Code

```bash
claude mcp add --transport http laravel-api https://<api-host>/api/mcp
```

Then run `/mcp` in Claude Code and choose **Authenticate**: the browser opens
the sign-in page, and Claude Code stores and renews the tokens itself. Its
callback is a `localhost` port; Passport matches a `localhost` redirect
exactly, so a sign-in on a different port registers again.

The `laravel-boost` entry already in `.mcp.json` is a separate, local
development tool server (stdio); it is unrelated to this endpoint.

## 11. claude.ai

Add a custom connector with the URL `https://<api-host>/api/mcp`, then connect
and sign in. claude.ai calls from its own servers, so the API must be
reachable over public HTTPS. Its callback is
`https://claude.ai/api/mcp/auth_callback`.

## 12. Adding a tool

1. `php artisan make:mcp-tool <Name>Tool --no-interaction`, then follow
   `EchoTool` for input validation and `GetCurrentUserTool` for a tool that
   acts as the user: `#[Name]` (snake_case), `#[Description]`, honest
   annotations, `schema()`, `outputSchema()`. Run `php artisan app:format`.
2. Add the class to `ApiServer::$tools`. Its position is its `tools/list`
   order.
3. A tool that reads or writes data identifies the caller with
   `$request->user()` and calls the existing Service layer with the same
   checks REST uses (`Gate::forUser($user)`, ownership-scoped repository
   queries). It never takes a user id from its arguments as proof of access.
4. Bump `ApiServer::VERSION` when the tool surface changes.
5. Cover it in `McpFeatureTest`.

## 13. Production considerations

- **HTTPS only**, at the edge. The MCP spec requires every OAuth endpoint to be
  HTTPS, and `APP_URL` must be the public https URL.
- **Edge rules:** route `/api/mcp`, `/oauth/*`, `/.well-known/oauth-authorization-server`
  and `/.well-known/oauth-protected-resource*` to the API; do not cache them.
- **Sessions:** the sign-in carries one browser session from the sign-in page
  to the consent page, so a scaled deployment needs a shared `SESSION_DRIVER`
  (`database` or `redis`, not `file`) and `SESSION_SECURE_COOKIE=true`.
- **Retention:** `passport:purge` (scheduled in `routes/console.php`) deletes
  expired and revoked tokens and codes. Registered clients are kept.
- **Errors:** `APP_DEBUG` must stay false; `app:check-config` fails
  production on `APP_DEBUG=true`.
- **Logs:** `Authorization` is redacted by `App\Logging\RedactSensitiveData`.
  Request bodies are not logged.
- **Consent phishing:** registration is open by design, so anyone can register
  a client under any name. The consent page shows the redirect address; set
  `MCP_OAUTH_REDIRECT_DOMAINS` to restrict which hosts may receive codes.
- **Upgrading from static client tokens:** `MCP_CLIENT_TOKENS` is gone (a
  leftover value is ignored). A client set up with a pasted bearer token now
  gets `401` with the sign-in challenge; re-add it by URL so it signs in.
- **Rolling back** to a release without this sign-in: once no pod of this
  release is serving, revoke the MCP tokens (`DEPLOYMENT.md` §14), because
  older releases accept them on REST.
- **A code or refresh token is spent by its first redemption attempt**, even
  one that then fails (a wrong verifier, a refresh asking for another scope):
  the client signs in again.
- **Consumers:** application-security-framework (`AGENTS.md`) is unaffected:
  it scans `/docs/api.json`, which documents only `api/` routes, and boots from
  `.env.example`, which needs no new value. No deploy order.
- **Refresh lifetime:** each refresh issues a new refresh token with the full
  `MCP_OAUTH_REFRESH_TOKEN_TTL_DAYS`, so a client that keeps refreshing stays
  connected until the user signs out everywhere, changes the password or is
  deleted. Passport has no absolute lifetime and no reuse detection.
- **Differences from the nestjs-api template** (same endpoints, configuration
  names and behaviour otherwise): the sign-in page is `/oauth/login` and
  consent is `/oauth/authorize`; tokens are bound by client and scope
  (nestjs-api: RFC 8707 audience); the refresh lifetime is renewed with each
  refresh (nestjs-api: absolute from sign-in, and a reused refresh token
  revokes the grant); there is no deactivation, only deletion; no Turnstile on
  the sign-in page; a `localhost` redirect must keep its port; a code is spent
  by its first redemption attempt; and `RATE_LIMIT_OAUTH` is configurable (nestjs-api:
  fixed limits in code).
- **Not configured here:** per-client disconnect (sign-out everywhere ends all
  of a user's connections), Client ID Metadata Documents, refresh-token reuse
  detection, per-user MCP rate buckets, and audit logging of tool calls.

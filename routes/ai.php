<?php

use App\Http\Middleware\AuthenticateMcpClient;
use App\Http\Middleware\NormalizeLegacyMcpErrorStatus;
use App\Mcp\Servers\ApiServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

/*
 * The MCP endpoint. See docs/mcp.md.
 *
 * Under /api like every other route, so existing edge and proxy rules cover it. The middleware wraps
 * the group rather than the route Mcp::web() returns, because that return value is only the POST
 * route: the GET and DELETE routes it also registers (405 answers for the 2025 transport) would
 * otherwise be reachable without a token. Order: rate limit (by address) first, then the client
 * token, so token guessing is counted; NormalizeLegacyMcpErrorStatus last, so it sees only
 * authenticated requests and the final MCP response.
 */
Route::middleware(['throttle:mcp', AuthenticateMcpClient::class, NormalizeLegacyMcpErrorStatus::class])->group(function () {
    Mcp::web('api/mcp', ApiServer::class);
});

<?php

use App\Http\Controllers\McpOAuthMetadataController;
use App\Http\Middleware\EnsureMcpToken;
use App\Http\Middleware\NormalizeLegacyMcpErrorStatus;
use App\Http\Middleware\ValidateMcpClientRegistration;
use App\Mcp\Servers\ApiServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

/*
 * The MCP endpoint and the OAuth discovery and registration its clients start from. See docs/mcp.md.
 *
 * The endpoint is under /api like every other route. Its middleware wraps the group rather than the route
 * Mcp::web() returns, because that return value is only the POST route: the GET and DELETE routes it also
 * registers (405 answers for the 2025 transport) would otherwise be reachable without a token. Order: rate
 * limit (by address) first, so token guessing is counted; then laravel/mcp's challenge header on every
 * method's 401 (it is otherwise attached to the POST route only); then the MCP access token; then
 * NormalizeLegacyMcpErrorStatus, so it sees only authenticated requests and the final MCP response.
 */
Route::middleware(['throttle:mcp', AddWwwAuthenticateHeader::class, EnsureMcpToken::class, NormalizeLegacyMcpErrorStatus::class])->group(function () {
    Mcp::web('api/mcp', ApiServer::class);
});

/*
 * Discovery and registration, at the origin root where OAuth clients look for them. The metadata documents
 * are this application's own (McpOAuthMetadataController: URLs pinned to the issuer, not the Host header),
 * under the route names laravel/mcp's 401 challenge looks up. Registration is laravel/mcp's controller behind
 * this application's redirect rule.
 */
Route::middleware('throttle:oauth')->group(function () {
    Route::get('.well-known/oauth-authorization-server', [McpOAuthMetadataController::class, 'authorizationServer'])
        ->name('mcp.oauth.authorization-server');
    Route::get('.well-known/oauth-protected-resource', [McpOAuthMetadataController::class, 'protectedResource'])
        ->name('mcp.oauth.protected-resource');
    Route::get('.well-known/oauth-protected-resource/{path}', [McpOAuthMetadataController::class, 'protectedResource'])
        ->where('path', 'api/mcp')
        ->name('mcp.oauth.protected-resource.nested');

    Route::post('oauth/register', OAuthRegisterController::class)
        ->middleware(ValidateMcpClientRegistration::class)
        ->name('mcp.oauth.register');
});

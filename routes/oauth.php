<?php

use App\Http\Controllers\OAuthLoginController;
use App\Http\Middleware\AddOAuthPageSecurityHeaders;
use App\Http\Middleware\EndOAuthSignInSession;
use App\Http\Middleware\EnsureFreshOAuthSignIn;
use App\Http\Middleware\PrepareMcpAuthorizationRequest;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

/*
 * The OAuth 2.1 authorization server MCP clients sign in through. See docs/mcp.md.
 *
 * Only the Passport routes the MCP flow needs, under Passport's own route names (Passport's controllers look
 * them up by name). Passport::ignoreRoutes() stays on, so its device-code, token-refresh and management
 * routes are never registered. Discovery and client registration are in routes/ai.php
 * (McpOAuthMetadataController, and laravel/mcp's registration controller).
 *
 * At the origin root, outside /api, because OAuth clients look for these endpoints there. The pages are in
 * the `web` group (session and CSRF) with the security headers a credential page needs; the token endpoint
 * is a plain POST that clients call directly.
 */
Route::prefix('oauth')->middleware('throttle:oauth')->group(function () {
    Route::post('token', [AccessTokenController::class, 'issueToken'])->name('passport.token');

    Route::middleware(['web', AddOAuthPageSecurityHeaders::class])->group(function () {
        Route::get('authorize', [AuthorizationController::class, 'authorize'])
            ->middleware(PrepareMcpAuthorizationRequest::class)
            ->name('passport.authorizations.authorize');

        Route::middleware(['auth:web', EnsureFreshOAuthSignIn::class, EndOAuthSignInSession::class])->group(function () {
            Route::post('authorize', [ApproveAuthorizationController::class, 'approve'])->name('passport.authorizations.approve');
            Route::delete('authorize', [DenyAuthorizationController::class, 'deny'])->name('passport.authorizations.deny');
        });

        Route::get('login', [OAuthLoginController::class, 'show'])->name('mcp.oauth.login');
        Route::post('login', [OAuthLoginController::class, 'signIn'])
            ->withoutMiddleware('throttle:oauth')
            ->middleware('throttle:sign-in')
            ->name('mcp.oauth.login.submit');
    });
});

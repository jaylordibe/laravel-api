<?php

use App\Utils\ResponseUtil;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Prevent from redirecting to login page (which triggers RouteNotFoundException)
        $middleware->redirectGuestsTo(function ($request) {
            return null;
        });

        /*
         * WHICH proxy headers to trust. WHICH PROXIES to trust is set in
         * config/trustedproxy.php, because this closure runs via
         * `afterResolving(HttpKernel::class)` — before the bootstrappers load
         * configuration — so `config()` returns nothing here. Headers are safe to
         * set in this closure precisely because they are integer constants and
         * need no configuration lookup.
         *
         * X-FORWARDED-HOST IS DELIBERATELY OMITTED from Laravel's default set.
         * Trusting it lets a client choose the host Laravel believes it is
         * serving, which poisons every absolute URL the application generates —
         * password-reset links, email-verification links and signed URLs all
         * point at an attacker's host while carrying a valid signature. The base
         * image's nginx does not forward that header either, so this closes the
         * same hole on the application side.
         *
         * X-Forwarded-Prefix is omitted for the same reason: it lets a client
         * prepend a path to every generated URL.
         */
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, $request) {
            return ResponseUtil::unauthorized();
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, $request) {
            return ResponseUtil::forbidden();
        });
    })->create();

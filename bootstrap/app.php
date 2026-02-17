<?php

use App\Utils\ResponseUtil;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, $request) {
            return ResponseUtil::unauthorized();
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, $request) {
            return ResponseUtil::forbidden();
        });
    })->create();

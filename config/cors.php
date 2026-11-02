<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // The MCP OAuth discovery documents and the endpoints a browser-based MCP client (the MCP Inspector UI)
    // calls cross-origin. The sign-in and consent pages are navigated to, never fetched, so they are not listed.
    'paths' => ['api/*', 'sanctum/csrf-cookie', '.well-known/oauth-*', 'oauth/register', 'oauth/token'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter(
        array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '')))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Authorization',
        'Content-Type',
        'Accept',
        'Origin',
        'X-Requested-With',
    ],

    'exposed_headers' => [
//        'X-RateLimit-Limit',
//        'X-RateLimit-Remaining',
//        'Retry-After',
    ],

    'max_age' => 600,

    'supports_credentials' => false,

];

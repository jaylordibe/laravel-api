<?php

/*
 * Only the laravel/mcp settings this application overrides; every other key keeps the package default
 * (vendor/laravel/mcp/config/mcp.php), merged in by its service provider.
 *
 * authorization_server: the OAuth issuer advertised in both metadata documents. Pinned to APP_URL rather
 * than taken from the request, so the issuer a client checks never depends on a Host header.
 *
 * Which redirects a client may register is decided by ValidateMcpClientRegistration
 * (custom.mcp.oauth.redirect_domains); the package's own `redirect_domains` stays at its permissive
 * default so the two never disagree.
 */
return [

    'authorization_server' => rtrim((string) env('APP_URL', 'http://localhost'), '/'),

];

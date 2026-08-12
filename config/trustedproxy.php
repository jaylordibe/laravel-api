<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted reverse proxies
    |--------------------------------------------------------------------------
    |
    | Addresses or CIDR ranges permitted to set X-Forwarded-* headers, as a
    | comma-separated string, or the literal '*' to trust any proxy.
    |
    | THIS FILENAME AND KEY ARE NOT ARBITRARY. Laravel's TrustProxies middleware
    | reads `config('trustedproxy.proxies')` as its fallback when no proxies were
    | set via `trustProxies(at: ...)`, and does the parsing itself — splitting a
    | comma-separated string, trimming each entry and handling '*' as a catch-all.
    | Using the framework's own hook means the value is read PER REQUEST, when
    | configuration is loaded, which is what makes it work at all: the
    | `withMiddleware()` closure in bootstrap/app.php runs via
    | `afterResolving(HttpKernel::class)`, BEFORE the bootstrappers load
    | configuration, so `config()` is unavailable there.
    |
    | EMPTY BY DEFAULT, and that is the security decision. Anything trusted here
    | can declare the client's IP address and whether the request arrived over
    | HTTPS — which is to say it controls what every rate limiter, IP allowlist
    | and audit log record sees. Trusting by default means trusting whatever else
    | can reach the container's port.
    |
    | Behind a load balancer this MUST be set, or Laravel builds http:// URLs for
    | an https:// site and attributes every request to the balancer's address.
    | On managed container platforms the balancer's address is usually neither
    | stable nor knowable, and nothing but the platform can route to the
    | container, so '*' is the practical value there. On a network where other
    | workloads can reach this container directly, enumerate the proxies instead.
    |
    | WHICH HEADERS are trusted is set separately, in bootstrap/app.php — there is
    | no config fallback for that, and it needs none, because it is built from
    | integer constants rather than configuration. X-Forwarded-Host is
    | deliberately excluded there.
    |
    */
    'proxies' => env('TRUSTED_PROXIES'),

];

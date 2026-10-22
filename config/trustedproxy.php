<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted reverse proxies
    |--------------------------------------------------------------------------
    |
    | Either '*' (believe the forwarded headers PHP receives) or null (believe
    | none). There is deliberately no address list here: the base image's nginx
    | is the trust boundary, and Laravel only decides whether to believe what
    | nginx hands it.
    |
    | TRUSTED_PROXIES belongs to the base image (jaylordibe/laravel-php): its
    | nginx reads it as SPACE-separated addresses, CIDRs or hostnames, and a
    | comma or '*' stops the container at start. When it is set, nginx resolves
    | the client into REMOTE_ADDR, passes X-Forwarded-Proto/Port only on
    | connections from a listed proxy, and never passes X-Forwarded-For on.
    | REMOTE_ADDR is then the client, not the proxy, so no address list could
    | match it here. '*' is what lets Laravel see the edge's scheme, and it is
    | safe because nginx has already removed every forwarded header a client sent
    | directly.
    |
    | TRUST_ANY_PROXY is for a platform whose load balancer has no knowable
    | address and is the only thing that can reach the container. nginx then
    | passes forwarded headers from every peer and '*' believes them, so it is
    | wrong anywhere other containers or the internet can reach this one.
    |
    | Neither set: nothing is believed — the safe state for a container exposed
    | directly. Behind a load balancer it means http:// URLs for an https:// site
    | and one rate-limit bucket for every client; see DEPLOYMENT.md §10.
    |
    | THIS FILENAME AND KEY ARE NOT ARBITRARY. Laravel's TrustProxies middleware
    | reads `config('trustedproxy.proxies')` as its fallback when no proxies were
    | set via `trustProxies(at: ...)`. Using the framework's own hook means the
    | value is read PER REQUEST, when configuration is loaded, which is what makes
    | it work at all: the `withMiddleware()` closure in bootstrap/app.php runs via
    | `afterResolving(HttpKernel::class)`, BEFORE the bootstrappers load
    | configuration, so `config()` is unavailable there.
    |
    | WHICH HEADERS are trusted is set separately, in bootstrap/app.php — there is
    | no config fallback for that, and it needs none, because it is built from
    | integer constants rather than configuration. X-Forwarded-Host is
    | deliberately excluded there.
    |
    */
    'proxies' => filled(env('TRUSTED_PROXIES')) || (bool) env('TRUST_ANY_PROXY', false) ? '*' : null,

];

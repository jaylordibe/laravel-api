<?php

namespace App\Utils;

/**
 * Parsing and policy helpers for the MCP endpoint's configuration (`MCP_ALLOWED_ORIGINS`,
 * `MCP_OAUTH_REDIRECT_DOMAINS`). One rule shared by the request middleware, the OAuth client
 * registration check and `app:check-config`.
 */
class McpConfigUtil
{

    /**
     * Hosts a native client's loopback redirect listens on (RFC 8252 §7.3).
     *
     * @var array<int, string>
     */
    private const array LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    /**
     * Parses exact browser origins (`https://host[:port]`) separated by commas.
     *
     * Returns `null` when an entry is not a bare origin (a path, a trailing slash or a wildcard).
     *
     * @param string $raw
     *
     * @return array<int, string>|null
     */
    public static function parseOrigins(string $raw): ?array
    {
        $origins = self::splitList($raw);

        foreach ($origins as $origin) {
            if (!self::isBareOrigin($origin)) {
                return null;
            }
        }

        return $origins;
    }

    /**
     * Parses `MCP_OAUTH_REDIRECT_DOMAINS`: the https origins an MCP client may register as its OAuth
     * redirect. Empty means any https origin; loopback redirects are always allowed and not listed.
     *
     * Returns `null` when an entry is not a bare https origin.
     *
     * @param string $raw
     *
     * @return array<int, string>|null
     */
    public static function parseRedirectOrigins(string $raw): ?array
    {
        $origins = self::parseOrigins($raw);

        if ($origins === null) {
            return null;
        }

        foreach ($origins as $origin) {
            if (!str_starts_with(strtolower($origin), 'https://')) {
                return null;
            }
        }

        return $origins;
    }

    /**
     * Whether the URI is a loopback redirect (`http://localhost`, `127.0.0.1` or `[::1]`, any port).
     *
     * @param string $uri
     *
     * @return bool
     */
    public static function isLoopbackRedirectUri(string $uri): bool
    {
        $parts = parse_url($uri);

        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        return strtolower($parts['scheme'] ?? '') === 'http'
            && in_array(strtolower($parts['host'] ?? ''), self::LOOPBACK_HOSTS, true);
    }

    /**
     * Whether a client registering through the MCP OAuth server may use the URI as a redirect. The MCP
     * authorization spec allows only loopback or https redirects; a non-empty `$allowedOrigins` narrows the
     * https ones. No fragment and no credentials in the URL.
     *
     * @param string $uri
     * @param array<int, string> $allowedOrigins
     *
     * @return bool
     */
    public static function isPermittedRedirectUri(string $uri, array $allowedOrigins): bool
    {
        $parts = parse_url($uri);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (self::isLoopbackRedirectUri($uri)) {
            return true;
        }

        if (strtolower($parts['scheme']) !== 'https') {
            return false;
        }

        $origin = 'https://' . strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return $allowedOrigins === [] || in_array($origin, array_map('strtolower', $allowedOrigins), true);
    }

    /**
     * Whether the entry is a bare origin: scheme, host and optional port, nothing else.
     *
     * @param string $entry
     *
     * @return bool
     */
    private static function isBareOrigin(string $entry): bool
    {
        return preg_match('#^https?://[a-z0-9.-]+(:\d{1,5})?$#i', $entry) === 1
            || preg_match('#^https?://\[[0-9a-f:]+\](:\d{1,5})?$#i', $entry) === 1;
    }

    /**
     * Splits a comma-separated list, trimming entries and dropping empty ones.
     *
     * @param string $raw
     *
     * @return array<int, string>
     */
    private static function splitList(string $raw): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $entry): bool => $entry !== ''
        ));
    }

}

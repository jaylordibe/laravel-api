<?php

namespace App\Utils;

/**
 * Parses and matches the MCP integration-client credentials (`MCP_CLIENT_TOKENS`).
 *
 * Each MCP client (n8n, Claude Code, ...) gets its own random bearer token. Only the SHA-256
 * digest of that token is configured; the raw token is generated once, handed to the client and
 * never stored by the API. SHA-256 rather than a slow password hash is deliberate: the tokens are
 * 256-bit random values, not human-chosen passwords, so there is nothing to brute-force offline.
 */
class McpClientCredentialUtil
{

    /**
     * A client name is a log-safe slug; a digest is SHA-256 in lowercase hex.
     */
    private const string ENTRY_PATTERN = '/^([a-z0-9][a-z0-9._-]{0,63}):([0-9a-f]{64})$/';

    /**
     * Parses `name:sha256hex` pairs separated by commas.
     *
     * Returns `[]` for an empty value (MCP disabled) and `null` when any entry is malformed or a
     * name or digest repeats, so a typo can never silently drop a client.
     *
     * @param string $raw
     *
     * @return array<string, string>|null client name => token digest
     */
    public static function parseCredentials(string $raw): ?array
    {
        $credentials = [];

        foreach (self::splitList($raw) as $entry) {
            if (!preg_match(self::ENTRY_PATTERN, $entry, $matches)) {
                return null;
            }

            [, $name, $digest] = $matches;

            if (array_key_exists($name, $credentials) || in_array($digest, $credentials, true)) {
                return null;
            }

            $credentials[$name] = $digest;
        }

        return $credentials;
    }

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
            if (!preg_match('#^https?://[a-z0-9.-]+(:\d{1,5})?$#i', $origin) && !preg_match('#^https?://\[[0-9a-f:]+\](:\d{1,5})?$#i', $origin)) {
                return null;
            }
        }

        return $origins;
    }

    /**
     * Returns the name of the client whose token digest matches, or `null`.
     *
     * Every configured digest is compared with `hash_equals` and the loop never exits early, so
     * response timing reveals neither a partial match nor which entry matched.
     *
     * @param string $token
     * @param array<string, string> $credentials client name => token digest
     *
     * @return string|null
     */
    public static function findClientByToken(string $token, array $credentials): ?string
    {
        $presentedDigest = hash('sha256', $token);
        $matchedName = null;

        foreach ($credentials as $name => $digest) {
            if (hash_equals($digest, $presentedDigest) && $matchedName === null) {
                $matchedName = $name;
            }
        }

        return $matchedName;
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

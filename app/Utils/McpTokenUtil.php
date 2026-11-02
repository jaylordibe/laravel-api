<?php

namespace App\Utils;

use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Contracts\ScopeAuthorizable;
use Laravel\Passport\Passport;

/**
 * Tells an MCP access token from a REST one. The single rule EnsureMcpToken (on /api/mcp) and RejectMcpToken
 * (on every REST route) both apply, so the two barriers can never disagree.
 *
 * A token is an MCP token when it was issued to a client that uses the authorization-code grant — only the MCP
 * sign-in issues those; REST sign-in issues personal access tokens — or when it carries the `mcp:use` scope.
 * The client is what decides, not the scope alone: Passport strips some requested scopes (`*`) from
 * authorization-code tokens, so a scope check by itself would let a scope-less MCP token through to REST.
 */
class McpTokenUtil
{

    /**
     * Whether the token was issued for MCP.
     *
     * @param ScopeAuthorizable|null $token
     *
     * @return bool
     */
    public static function isMcpToken(?ScopeAuthorizable $token): bool
    {
        if ($token === null) {
            return false;
        }

        if ($token->can(Registrar::OAUTH_SCOPE)) {
            return true;
        }

        return self::issuedThroughAuthorizationCode($token);
    }

    /**
     * Whether the token is a usable MCP token: issued through the MCP sign-in AND carrying `mcp:use`.
     *
     * @param ScopeAuthorizable|null $token
     *
     * @return bool
     */
    public static function isUsableOnMcp(?ScopeAuthorizable $token): bool
    {
        return $token !== null
            && $token->can(Registrar::OAUTH_SCOPE)
            && self::issuedThroughAuthorizationCode($token);
    }

    /**
     * Whether the token's client uses the authorization-code grant.
     *
     * @param ScopeAuthorizable $token
     *
     * @return bool
     */
    private static function issuedThroughAuthorizationCode(ScopeAuthorizable $token): bool
    {
        if (!$token instanceof AccessToken || empty($token->oauth_client_id)) {
            return false;
        }

        $client = Passport::client()->newQuery()->find($token->oauth_client_id);

        return $client !== null && $client->hasGrantType('authorization_code');
    }

}

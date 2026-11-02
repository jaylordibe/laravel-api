<?php

namespace App\Repositories;

use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Passport;

/**
 * Passport's refresh-token storage, with the "is it still unused?" check made atomic, for the same reason as
 * SingleUseAuthCodeRepository: two simultaneous refreshes with one refresh token must not both get a new pair.
 */
class SingleUseRefreshTokenRepository extends RefreshTokenRepository
{

    /**
     * Claim the refresh token: `false` (not revoked) for exactly one caller, `true` for every other.
     *
     * @param string $tokenId
     *
     * @return bool
     */
    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        return Passport::refreshToken()->newQuery()->whereKey($tokenId)->where('revoked', false)->update(['revoked' => true]) === 0;
    }

}

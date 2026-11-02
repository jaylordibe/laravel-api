<?php

namespace App\Repositories;

use Laravel\Passport\Bridge\AuthCodeRepository;
use Laravel\Passport\Passport;

/**
 * Passport's authorization-code storage, with the "is it still unused?" check made atomic.
 *
 * league/oauth2-server checks a code is unrevoked, issues tokens, and only then revokes it, with nothing in
 * between: two simultaneous redemptions of one code both pass the check and each get a token pair. Here the
 * check claims the code (one conditional UPDATE), so only the first redemption sees it unused. A code is
 * therefore spent by its first redemption attempt, even one whose PKCE verifier then fails.
 */
class SingleUseAuthCodeRepository extends AuthCodeRepository
{

    /**
     * Claim the code: `false` (not revoked) for exactly one caller, `true` for every other.
     *
     * @param string $codeId
     *
     * @return bool
     */
    public function isAuthCodeRevoked(string $codeId): bool
    {
        return Passport::authCode()->newQuery()->whereKey($codeId)->where('revoked', false)->update(['revoked' => true]) === 0;
    }

}

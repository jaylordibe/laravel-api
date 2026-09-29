<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\RedirectResponse;

/**
 * A verification link that is invalid, tampered with or expired. The link is opened in a browser, so it
 * lands on the client app with `verified=0`, where the user can request a new one.
 */
class InvalidVerificationLinkException extends Exception
{

    /**
     * Render the exception into an HTTP response.
     *
     * @return RedirectResponse
     */
    public function render(): RedirectResponse
    {
        return redirect()->away(config('custom.app_frontend_url') . '/?verified=0');
    }

}

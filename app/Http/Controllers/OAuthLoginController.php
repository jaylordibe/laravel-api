<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Utils\AppUtil;
use App\Utils\McpSignInUtil;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Passport\Client;

/**
 * The sign-in page of the MCP OAuth flow (routes/oauth.php): where Passport's authorize endpoint sends a
 * browser that is not signed in, and where it returns once it is.
 *
 * Like AuthController it has no service layer (the one auth exception to the pipeline), and it applies
 * the same rules as REST sign-in: email or username, the same generic failure for a wrong password and an
 * unknown account, a verified email required, the `sign-in` rate limiter. One difference is deliberate:
 * the password is checked with `validate` and the email-verified gate applied BEFORE `login`, so an
 * unverified account never holds the browser session Passport's authorize step trusts.
 */
class OAuthLoginController extends Controller
{

    /**
     * The failure message for a wrong password and an unknown account alike, as REST sign-in answers.
     */
    private const string INVALID_CREDENTIALS = 'Invalid username or password';

    /**
     * Show the sign-in form.
     *
     * @param Request $request
     *
     * @return View
     */
    public function show(Request $request): View
    {
        return view('mcp.login', ['clientName' => $this->requestingClientName($request)]);
    }

    /**
     * Check the credentials, start the browser session and return to the authorization request.
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function signIn(Request $request): RedirectResponse
    {
        $identifier = $request->input('identifier');
        $password = $request->input('password');

        if (!is_string($identifier) || !is_string($password) || trim($identifier) === '' || $password === '') {
            return $this->refuse(self::INVALID_CREDENTIALS);
        }

        // Lower-cased to match the canonical form User stores, as AuthController::signIn does.
        $identifier = Str::lower(trim($identifier));
        $credentials = [
            AppUtil::isValidEmail($identifier) ? 'email' : 'username' => $identifier,
            'password' => $password,
        ];
        $guard = Auth::guard('web');

        if (!$guard->validate($credentials)) {
            return $this->refuse(self::INVALID_CREDENTIALS);
        }

        /** @var User $user */
        $user = $guard->getProvider()->retrieveByCredentials($credentials);

        if (empty($user->email_verified_at)) {
            return $this->refuse('Email not yet verified. Please check your email and verify.');
        }

        $guard->login($user);
        $request->session()->regenerate();
        McpSignInUtil::recordSignIn($request);

        return redirect()->intended(route('mcp.oauth.login'));
    }

    /**
     * Back to the form with the message, keeping the identifier.
     *
     * @param string $message
     *
     * @return RedirectResponse
     */
    private function refuse(string $message): RedirectResponse
    {
        return redirect()->route('mcp.oauth.login')
            ->withErrors(['identifier' => $message])
            ->onlyInput('identifier');
    }

    /**
     * The registered name of the client whose authorization request brought the browser here, or `null`.
     *
     * @param Request $request
     *
     * @return string|null
     */
    private function requestingClientName(Request $request): ?string
    {
        $intended = $request->session()->get('url.intended');

        if (!is_string($intended)) {
            return null;
        }

        parse_str((string) parse_url($intended, PHP_URL_QUERY), $query);
        $clientId = $query['client_id'] ?? null;

        return is_string($clientId) ? Client::query()->find($clientId)?->name : null;
    }

}

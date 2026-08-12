<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthRequest;
use App\Http\Requests\GenericRequest;
use App\Utils\ResponseUtil;
use App\Utils\AppUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Passport\PersonalAccessTokenResult;

class AuthController extends Controller
{

    public function __construct()
    {
        $this->middleware('guest')->except('signOut');
    }

    /**
     * Log the user in to the application.
     */
    public function signIn(AuthRequest $request): JsonResponse
    {
        $authData = $request->toData();
        $identifierField = AppUtil::isValidEmail($authData->identifier) ? 'email' : 'username';
        // Lower-cased to match the canonical form User stores. Auth::attempt builds
        // its own WHERE clause straight from these credentials, so the model's
        // set-mutator never sees it — on PostgreSQL an un-normalised identifier is
        // a case-sensitive comparison and sign-in fails for the right password.
        $credentials = [
            $identifierField => Str::lower(trim($authData->identifier)),
            'password' => $authData->password
        ];

        if (!Auth::attempt($credentials, $authData->remember)) {
            return ResponseUtil::error('Invalid username or password');
        }

        $user = Auth::user();

        if (empty($user->email_verified_at)) {
            return ResponseUtil::error('Email not yet verified. Please check your email and verify.');
        }

        /** @var PersonalAccessTokenResult $personalAccessTokenResult */
        $personalAccessTokenResult = $user->createToken(config('app.name'));

        $response = [
            'token' => $personalAccessTokenResult->accessToken
        ];

        return ResponseUtil::json($response);
    }

    /**
     * Log the user out of the application.
     */
    public function signOut(GenericRequest $request): JsonResponse
    {
        Auth::user()->token()->delete();

        return ResponseUtil::success('Logout successful.');
    }

}

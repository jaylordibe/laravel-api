<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthRequest;
use App\Http\Requests\GenericRequest;
use App\Services\UserService;
use App\Utils\ResponseUtil;
use App\Utils\AppUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

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
        $credentials = [
            $identifierField => $authData->identifier,
            'password' => $authData->password
        ];

        if (!Auth::attempt($credentials, $authData->remember)) {
            return ResponseUtil::error('Invalid username or password');
        }

        $user = Auth::user();

        if (empty($user->email_verified_at)) {
            return ResponseUtil::error('Email not yet verified. Please check your email and verify.');
        }

        $response = [
            'token' => $user->createToken(config('app.name'))->accessToken
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

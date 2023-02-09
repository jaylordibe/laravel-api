<?php

use App\Constants\RoutePatternConstant;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

# Public Auth
Route::post('authenticate', [AuthController::class, 'authenticate']);

Route::middleware('auth:api')->group(function () {
    # Auth
    Route::post('sign-out', [AuthController::class, 'signOut']);

    # User
    Route::post('users', [UserController::class, 'create']);
    Route::get('users', [UserController::class, 'get']);
    Route::get('users/auth', [UserController::class, 'getAuthUser']);
    Route::get('users/{userId}', [UserController::class, 'getById'])->where('userId', RoutePatternConstant::NUMERIC);
    Route::put('users/{userId}', [UserController::class, 'update'])->where('userId', RoutePatternConstant::NUMERIC);
    Route::delete('users/{userId}', [UserController::class, 'delete'])->where('userId', RoutePatternConstant::NUMERIC);
});

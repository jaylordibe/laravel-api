<?php

use App\Constants\RoutePatternConstant;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public Routes
Route::post('auth/sign-in', [AuthController::class, 'signIn']);

// Authenticated Routes
Route::middleware('auth:api')->group(function () {
    // Auth
    Route::post('auth/sign-out', [AuthController::class, 'signOut']);

    // User
    Route::post('users', [UserController::class, 'create']);
    Route::get('users', [UserController::class, 'getPaginated']);
    Route::get('users/auth', [UserController::class, 'getAuthUser']);
    Route::put('users/auth/username', [UserController::class, 'updateAuthUserName']);
    Route::put('users/auth/email', [UserController::class, 'updateAuthUserEmail']);
    Route::get('users/{id}', [UserController::class, 'getById'])->where('id', RoutePatternConstant::NUMERIC);
    Route::put('users/{id}', [UserController::class, 'update'])->where('id', RoutePatternConstant::NUMERIC);
    Route::delete('users/{id}', [UserController::class, 'delete'])->where('id', RoutePatternConstant::NUMERIC);

    // Address
    Route::post('addresses', [AddressController::class, 'create']);
    Route::get('addresses', [AddressController::class, 'getPaginated']);
    Route::get('addresses/{id}', [AddressController::class, 'getById'])->where('id', RoutePatternConstant::NUMERIC);
    Route::put('addresses/{id}', [AddressController::class, 'update'])->where('id', RoutePatternConstant::NUMERIC);
    Route::delete('addresses/{id}', [AddressController::class, 'delete'])->where('id', RoutePatternConstant::NUMERIC);
});

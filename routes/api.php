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

    // CRUD routes for User
    Route::post('users', [UserController::class, 'create']);
    Route::get('users', [UserController::class, 'getPaginated']);
    Route::get('users/auth', [UserController::class, 'getAuthUser']);
    Route::put('users/auth/username', [UserController::class, 'updateAuthUserName']);
    Route::put('users/auth/email', [UserController::class, 'updateAuthUserEmail']);
    Route::get('users/{userId}', [UserController::class, 'getById'])->where('userId', RoutePatternConstant::NUMERIC);
    Route::put('users/{userId}', [UserController::class, 'update'])->where('userId', RoutePatternConstant::NUMERIC);
    Route::delete('users/{userId}', [UserController::class, 'delete'])->where('userId', RoutePatternConstant::NUMERIC);

    // CRUD routes for Address
    Route::post('addresses', [AddressController::class, 'create']);
    Route::get('addresses', [AddressController::class, 'getPaginated']);
    Route::get('addresses/{addressId}', [AddressController::class, 'getById'])->where('addressId', RoutePatternConstant::NUMERIC);
    Route::put('addresses/{addressId}', [AddressController::class, 'update'])->where('addressId', RoutePatternConstant::NUMERIC);
    Route::delete('addresses/{addressId}', [AddressController::class, 'delete'])->where('addressId', RoutePatternConstant::NUMERIC);
});

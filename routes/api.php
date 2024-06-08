<?php

use App\Constants\RoutePatternConstant;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\AppVersionController;
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
Route::post('users/sign-up', [UserController::class, 'signUp']);
Route::get('app-versions/latest', [AppVersionController::class, 'getLatest']);

// Authenticated Routes
Route::middleware('auth:api')->group(function () {
    // Auth
    Route::post('auth/sign-out', [AuthController::class, 'signOut']);

    // CRUD routes for AppVersion
    Route::prefix('app-versions')->group(function () {
        Route::post('/', [AppVersionController::class, 'create']);
        Route::get('/', [AppVersionController::class, 'getPaginated']);
        Route::get('/{appVersionId}', [AppVersionController::class, 'getById'])->where('appVersionId', RoutePatternConstant::NUMERIC);
        Route::put('/{appVersionId}', [AppVersionController::class, 'update'])->where('appVersionId', RoutePatternConstant::NUMERIC);
        Route::delete('/{appVersionId}', [AppVersionController::class, 'delete'])->where('appVersionId', RoutePatternConstant::NUMERIC);
    });

    // CRUD routes for User
    Route::prefix('users')->group(function () {
        Route::post('/', [UserController::class, 'create']);
        Route::get('/', [UserController::class, 'getPaginated']);
        Route::get('/auth', [UserController::class, 'getAuthUser']);
        Route::put('/auth/username', [UserController::class, 'updateAuthUserName']);
        Route::put('/auth/email', [UserController::class, 'updateAuthUserEmail']);
        Route::get('/{userId}', [UserController::class, 'getById'])->where('userId', RoutePatternConstant::NUMERIC);
        Route::put('/{userId}', [UserController::class, 'update'])->where('userId', RoutePatternConstant::NUMERIC);
        Route::delete('/{userId}', [UserController::class, 'delete'])->where('userId', RoutePatternConstant::NUMERIC);
    });

    // CRUD routes for Address
    Route::prefix('addresses')->group(function () {
        Route::post('/', [AddressController::class, 'create']);
        Route::get('/', [AddressController::class, 'getPaginated']);
        Route::get('/{addressId}', [AddressController::class, 'getById'])->where('addressId', RoutePatternConstant::NUMERIC);
        Route::put('/{addressId}', [AddressController::class, 'update'])->where('addressId', RoutePatternConstant::NUMERIC);
        Route::delete('/{addressId}', [AddressController::class, 'delete'])->where('addressId', RoutePatternConstant::NUMERIC);
    });
});

<?php

use App\Constants\RoutePatternConstant;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\AppVersionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\JobStatusController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\ThisIsTestController;

// Public Routes
Route::post('auth/sign-in', [AuthController::class, 'signIn']);
Route::post('users/sign-up', [UserController::class, 'signUp']);
Route::get('app-versions/latest', [AppVersionController::class, 'getLatest']);
Route::get('email/verify/{id}', [UserController::class, 'verifyEmail']);

// Authenticated Routes
Route::middleware('auth:api')->group(function () {
    // Auth
    Route::post('auth/sign-out', [AuthController::class, 'signOut']);

    // AppVersion routes
    Route::prefix('app-versions')->group(function () {
        Route::post('/', [AppVersionController::class, 'create']);
        Route::get('/', [AppVersionController::class, 'getPaginated']);
        Route::get('/{appVersionId}', [AppVersionController::class, 'getById'])->where('appVersionId', RoutePatternConstant::NUMERIC);
        Route::put('/{appVersionId}', [AppVersionController::class, 'update'])->where('appVersionId', RoutePatternConstant::NUMERIC);
        Route::delete('/{appVersionId}', [AppVersionController::class, 'delete'])->where('appVersionId', RoutePatternConstant::NUMERIC);
    });

    // User routes
    Route::prefix('users')->group(function () {
        Route::post('/', [UserController::class, 'create']);
        Route::get('/', [UserController::class, 'getPaginated']);
        Route::get('/auth', [UserController::class, 'getAuthUser']);
        Route::put('/auth/username', [UserController::class, 'updateAuthUsername']);
        Route::put('/auth/email', [UserController::class, 'updateAuthUserEmail']);
        Route::put('/auth/password', [UserController::class, 'updateAuthUserPassword']);
        Route::post('/auth/profile-photo', [UserController::class, 'updateAuthUserProfilePhoto']);
        Route::put('/{userId}/password', [UserController::class, 'updatePassword'])->where('userId', RoutePatternConstant::NUMERIC);
        Route::get('/{userId}', [UserController::class, 'getById'])->where('userId', RoutePatternConstant::NUMERIC);
        Route::put('/{userId}', [UserController::class, 'update'])->where('userId', RoutePatternConstant::NUMERIC);
        Route::delete('/{userId}', [UserController::class, 'delete'])->where('userId', RoutePatternConstant::NUMERIC);
    });

    // Address routes
    Route::prefix('addresses')->group(function () {
        Route::post('/', [AddressController::class, 'create']);
        Route::get('/', [AddressController::class, 'getPaginated']);
        Route::get('/{addressId}', [AddressController::class, 'getById'])->where('addressId', RoutePatternConstant::NUMERIC);
        Route::put('/{addressId}', [AddressController::class, 'update'])->where('addressId', RoutePatternConstant::NUMERIC);
        Route::delete('/{addressId}', [AddressController::class, 'delete'])->where('addressId', RoutePatternConstant::NUMERIC);
    });

    // DeviceToken routes
    Route::prefix('device-tokens')->group(function () {
        Route::post('/', [DeviceTokenController::class, 'create']);
        Route::get('/', [DeviceTokenController::class, 'getPaginated']);
        Route::get('/{deviceTokenId}', [DeviceTokenController::class, 'getById'])->where('deviceTokenId', RoutePatternConstant::NUMERIC);
        Route::put('/{deviceTokenId}', [DeviceTokenController::class, 'update'])->where('deviceTokenId', RoutePatternConstant::NUMERIC);
        Route::delete('/{deviceTokenId}', [DeviceTokenController::class, 'delete'])->where('deviceTokenId', RoutePatternConstant::NUMERIC);
    });

    // JobStatus routes
    Route::prefix('job-statuses')->group(function () {
        Route::get('/{jobStatusId}', [JobStatusController::class, 'getById'])->where('jobStatusId', RoutePatternConstant::NUMERIC);
    });

	// ThisIsTest routes
	Route::prefix('this-is-tests')->group(function () {
    	Route::post('/', [ThisIsTestController::class, 'create']);
    	Route::get('/', [ThisIsTestController::class, 'getPaginated']);
    	Route::get('/{thisIsTestId}', [ThisIsTestController::class, 'getById'])->where('thisIsTestId', RoutePatternConstant::NUMERIC);
    	Route::put('/{thisIsTestId}', [ThisIsTestController::class, 'update'])->where('thisIsTestId', RoutePatternConstant::NUMERIC);
    	Route::delete('/{thisIsTestId}', [ThisIsTestController::class, 'delete'])->where('thisIsTestId', RoutePatternConstant::NUMERIC);
	});
});

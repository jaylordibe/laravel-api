<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AppVersionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConstantController;
use App\Http\Controllers\JobStatusController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\ActivityLogController;

// Public Routes
Route::middleware(['throttle:public'])->group(function () {
    Route::get('app-versions/latest', [AppVersionController::class, 'getLatest']);
});

// Sensitive Routes
Route::middleware(['throttle:sensitive'])->group(function () {
    Route::post('auth/sign-in', [AuthController::class, 'signIn']);
    Route::post('users/sign-up', [UserController::class, 'signUp']);
    Route::get('email/verify/{id}', [UserController::class, 'verifyEmail'])->name('verification.verify');
});

// Authenticated Routes
Route::middleware(['auth:api', 'throttle:api'])->group(function () {
    // Auth
    Route::post('auth/sign-out', [AuthController::class, 'signOut']);

    // Constant routes
    Route::prefix('constants')->group(function () {
        Route::get('/activity-log-type', [ConstantController::class, 'getActivityLogTypes']);
        Route::get('/app-platform', [ConstantController::class, 'getAppPlatforms']);
        Route::get('/device-os', [ConstantController::class, 'getDeviceOs']);
        Route::get('/device-type', [ConstantController::class, 'getDeviceTypes']);
        Route::get('/spreadsheet-reader-type', [ConstantController::class, 'getSpreadsheetReaderTypes']);
        Route::get('/user-role', [ConstantController::class, 'getUserRoles']);
    });

    // AppVersion routes
    Route::prefix('app-versions')->group(function () {
        Route::post('/', [AppVersionController::class, 'create']);
        Route::get('/', [AppVersionController::class, 'getPaginated']);
        Route::get('/{appVersionId}', [AppVersionController::class, 'getById'])->where('appVersionId', config('custom.numeric_regex'));
        Route::put('/{appVersionId}', [AppVersionController::class, 'update'])->where('appVersionId', config('custom.numeric_regex'));
        Route::delete('/{appVersionId}', [AppVersionController::class, 'delete'])->where('appVersionId', config('custom.numeric_regex'));
    });

    // User routes
    Route::prefix('users')->group(function () {
        Route::post('/', [UserController::class, 'create']);
        Route::get('/', [UserController::class, 'getPaginated']);
        Route::get('/auth', [UserController::class, 'getAuthUser']);
        Route::put('/auth/username', [UserController::class, 'updateAuthUsername']);
        Route::put('/auth/email', [UserController::class, 'updateAuthUserEmail']);
        Route::put('/auth/password', [UserController::class, 'updateAuthUserPassword']);
        Route::post('/auth/profile-image', [UserController::class, 'updateAuthUserProfileImage']);
        Route::put('/{userId}/password', [UserController::class, 'updatePassword'])->where('userId', config('custom.numeric_regex'));
        Route::get('/{userId}', [UserController::class, 'getById'])->where('userId', config('custom.numeric_regex'));
        Route::put('/{userId}', [UserController::class, 'update'])->where('userId', config('custom.numeric_regex'));
        Route::delete('/{userId}', [UserController::class, 'delete'])->where('userId', config('custom.numeric_regex'));
    });

    // Address routes
    Route::prefix('addresses')->group(function () {
        Route::post('/', [AddressController::class, 'create']);
        Route::get('/', [AddressController::class, 'getPaginated']);
        Route::get('/{addressId}', [AddressController::class, 'getById'])->where('addressId', config('custom.numeric_regex'));
        Route::put('/{addressId}', [AddressController::class, 'update'])->where('addressId', config('custom.numeric_regex'));
        Route::delete('/{addressId}', [AddressController::class, 'delete'])->where('addressId', config('custom.numeric_regex'));
    });

    // DeviceToken routes
    Route::prefix('device-tokens')->group(function () {
        Route::post('/', [DeviceTokenController::class, 'create']);
        Route::get('/', [DeviceTokenController::class, 'getPaginated']);
        Route::get('/{deviceTokenId}', [DeviceTokenController::class, 'getById'])->where('deviceTokenId', config('custom.numeric_regex'));
        Route::put('/{deviceTokenId}', [DeviceTokenController::class, 'update'])->where('deviceTokenId', config('custom.numeric_regex'));
        Route::delete('/{deviceTokenId}', [DeviceTokenController::class, 'delete'])->where('deviceTokenId', config('custom.numeric_regex'));
    });

    // JobStatus routes
    Route::prefix('job-statuses')->group(function () {
        Route::get('/{jobStatusId}', [JobStatusController::class, 'getById'])->where('jobStatusId', config('custom.numeric_regex'));
    });

    // Activity routes
    Route::prefix('activity-logs')->group(function () {
        Route::post('/', [ActivityLogController::class, 'create']);
        Route::get('/', [ActivityLogController::class, 'getPaginated']);
    });
});

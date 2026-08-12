<?php

use App\Http\Controllers\AppVersionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConstantController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\JobStatusController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\ActivityLogController;

// Readiness probe. Public by necessity — a load balancer cannot present a bearer
// token — and therefore deliberately discloses nothing beyond a per-dependency
// boolean.
//
// DELIBERATELY NOT THROTTLED. Laravel's rate limiter resolves through the default
// cache store, which production requires to be Redis — so putting `throttle:*` in
// front of this endpoint means that when Redis is down, the limiter throws before
// the controller runs and the probe returns 500 instead of the 503 that names
// Redis as the failed dependency. A readiness check that breaks in exactly the
// outage it exists to report is worse than no readiness check.
//
// The DoS surface this leaves is bounded by the probes themselves: two round
// trips, each with a hard timeout (see App\Utils\HealthUtil), and no output that
// varies with input. Restrict the path at the edge if your platform exposes it to
// the internet.
//
// LIVENESS is separate and lives at GET /up (bootstrap/app.php). See
// HealthController for why the two must not be merged.
Route::get('health/ready', [HealthController::class, 'ready']);

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
        Route::put('/auth', [UserController::class, 'updateAuthUserInfo']);
        Route::delete('/auth', [UserController::class, 'deleteAuthUser']);
        Route::put('/auth/username', [UserController::class, 'updateAuthUsername']);
        Route::put('/auth/email', [UserController::class, 'updateAuthUserEmail']);
        Route::put('/auth/password', [UserController::class, 'updateAuthUserPassword']);
        Route::post('/auth/profile-image', [UserController::class, 'updateAuthUserProfileImage']);
        Route::put('/{userId}/password', [UserController::class, 'updatePassword'])->where('userId', config('custom.numeric_regex'));
        Route::get('/{userId}', [UserController::class, 'getById'])->where('userId', config('custom.numeric_regex'));
        Route::put('/{userId}', [UserController::class, 'update'])->where('userId', config('custom.numeric_regex'));
        Route::delete('/{userId}', [UserController::class, 'delete'])->where('userId', config('custom.numeric_regex'));
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

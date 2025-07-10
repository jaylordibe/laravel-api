<?php

namespace App\Http\Controllers;

use App\Enums\ActivityLogType;
use App\Enums\AppPlatform;
use App\Enums\DeviceOs;
use App\Enums\DeviceType;
use App\Enums\SpreadsheetReaderType;
use App\Enums\UserRole;
use App\Utils\ResponseUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ConstantController extends Controller
{

    /**
     * Get activity log types.
     *
     * @return JsonResponse
     */
    public function getActivityLogTypes(): JsonResponse
    {
        return ResponseUtil::json(ActivityLogType::cases());
    }

    /**
     * Get app platforms.
     *
     * @return JsonResponse
     */
    public function getAppPlatforms(): JsonResponse
    {
        return ResponseUtil::json(AppPlatform::cases());
    }

    /**
     * Get device OS.
     *
     * @return JsonResponse
     */
    public function getDeviceOs(): JsonResponse
    {
        return ResponseUtil::json(DeviceOs::cases());
    }

    /**
     * Get device types.
     *
     * @return JsonResponse
     */
    public function getDeviceTypes(): JsonResponse
    {
        return ResponseUtil::json(DeviceType::cases());
    }

    /**
     * Get spreadsheet reader types.
     *
     * @return JsonResponse
     */
    public function getSpreadsheetReaderTypes(): JsonResponse
    {
        return ResponseUtil::json(SpreadsheetReaderType::cases());
    }

    /**
     * Get user roles.
     *
     * @return JsonResponse
     */
    public function getUserRoles(): JsonResponse
    {
        return ResponseUtil::json(UserRole::cases());
    }

}

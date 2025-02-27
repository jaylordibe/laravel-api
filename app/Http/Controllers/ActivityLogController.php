<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenericRequest;
use App\Http\Requests\ActivityLogRequest;
use App\Http\Resources\ActivityLogResource;
use App\Services\ActivityLogService;
use App\Utils\ResponseUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;

class ActivityLogController extends Controller
{

    public function __construct(
        private readonly ActivityLogService $activityService
    )
    {
    }

    /**
     * Create activity.
     *
     * @param ActivityLogRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function create(ActivityLogRequest $request): JsonResponse|JsonResource
    {
        $serviceResponse = $this->activityService->create($request->toData());

        if ($serviceResponse->failed()) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(ActivityLogResource::class, $serviceResponse->data);
    }

    /**
     * Get paginated activities.
     *
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function getPaginated(GenericRequest $request): JsonResponse|JsonResource
    {
        $activityFilterData = ActivityLogRequest::createFrom($request)->toFilterData();
        $serviceResponse = $this->activityService->getPaginated($activityFilterData);

        if ($serviceResponse->failed()) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(ActivityLogResource::class, $serviceResponse->data);
    }

}

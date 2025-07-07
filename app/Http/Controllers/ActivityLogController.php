<?php

namespace App\Http\Controllers;

use App\Exceptions\BadRequestException;
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
     * @throws BadRequestException
     */
    public function create(ActivityLogRequest $request): JsonResponse|JsonResource
    {
        $activity = $this->activityService->create($request->toData());

        return ResponseUtil::resource(ActivityLogResource::class, $activity);
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
        $activities = $this->activityService->getPaginated($activityFilterData);

        return ResponseUtil::resource(ActivityLogResource::class, $activities);
    }

}

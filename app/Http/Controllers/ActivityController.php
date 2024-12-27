<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenericRequest;
use App\Http\Requests\ActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Services\ActivityService;
use App\Utils\ResponseUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;

class ActivityController extends Controller
{

    public function __construct(
        private readonly ActivityService $activityService
    )
    {
    }

    /**
     * Create activity.
     *
     * @param ActivityRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function create(ActivityRequest $request): JsonResponse|JsonResource
    {
        $serviceResponse = $this->activityService->create($request->toData());

        if ($serviceResponse->failed()) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(ActivityResource::class, $serviceResponse->data);
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
        $activityFilterData = ActivityRequest::createFrom($request)->toFilterData();
        $serviceResponse = $this->activityService->getPaginated($activityFilterData);

        if ($serviceResponse->failed()) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(ActivityResource::class, $serviceResponse->data);
    }

}

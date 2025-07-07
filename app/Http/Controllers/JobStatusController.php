<?php

namespace App\Http\Controllers;

use App\Exceptions\BadRequestException;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\JobStatusResource;
use App\Services\JobStatusService;
use App\Utils\ResponseUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;

class JobStatusController extends Controller
{

    public function __construct(
        private readonly JobStatusService $jobStatusService
    )
    {
    }

    /**
     * Get job status by id.
     *
     * @param GenericRequest $request
     * @param int $jobStatusId
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function getById(GenericRequest $request, int $jobStatusId): JsonResponse|JsonResource
    {
        $jobStatus = $this->jobStatusService->getById($jobStatusId);

        return ResponseUtil::resource(JobStatusResource::class, $jobStatus);
    }

}

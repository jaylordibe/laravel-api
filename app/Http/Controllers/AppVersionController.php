<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenericRequest;
use App\Http\Requests\AppVersionRequest;
use App\Http\Resources\AppVersionResource;
use App\Services\AppVersionService;
use App\Utils\ResponseUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;

class AppVersionController extends Controller
{

    /**
     * AppVersionController constructor.
     *
     * @param AppVersionService $appVersionService
     */
    public function __construct(
        private readonly AppVersionService $appVersionService
    )
    {
    }

    /**
     * Create app version.
     *
     * @param AppVersionRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function create(AppVersionRequest $request): JsonResponse|JsonResource
    {
        $serviceResponse = $this->appVersionService->create($request->toData());

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(AppVersionResource::class, $serviceResponse->data);
    }

    /**
     * Get paginated app versions.
     *
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function getPaginated(GenericRequest $request): JsonResponse|JsonResource
    {
        $appVersionData = AppVersionRequest::createFrom($request)->toFilterData();
        $serviceResponse = $this->appVersionService->getPaginated($appVersionData);

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(AppVersionResource::class, $serviceResponse->data);
    }

    /**
     * Get app version by id.
     *
     * @param GenericRequest $request
     * @param int $appVersionId
     *
     * @return JsonResponse|JsonResource
     */
    public function getById(GenericRequest $request, int $appVersionId): JsonResponse|JsonResource
    {
        $relations = $request->getRelations();
        $serviceResponse = $this->appVersionService->getById($appVersionId, $relations);

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(AppVersionResource::class, $serviceResponse->data);
    }

    /**
     * Update app version.
     *
     * @param AppVersionRequest $request
     * @param int $appVersionId
     *
     * @return JsonResponse|JsonResource
     */
    public function update(AppVersionRequest $request, int $appVersionId): JsonResponse|JsonResource
    {
        $serviceResponse = $this->appVersionService->update($request->toData());

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(AppVersionResource::class, $serviceResponse->data);
    }

    /**
     * Delete app version.
     *
     * @param GenericRequest $request
     * @param int $appVersionId
     *
     * @return JsonResponse
     */
    public function delete(GenericRequest $request, int $appVersionId): JsonResponse
    {
        $serviceResponse = $this->appVersionService->delete($appVersionId);

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::success($serviceResponse->message);
    }

    /**
     * Get the latest app version.
     *
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function getLatest(GenericRequest $request): JsonResponse|JsonResource
    {
        $platform = $request->getInputAsString('platform');

        if (empty($platform)) {
            return ResponseUtil::error('Platform is required.');
        }

        $serviceResponse = $this->appVersionService->getLatest($platform);

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(AppVersionResource::class, $serviceResponse->data);
    }

}

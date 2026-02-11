<?php

namespace App\Http\Controllers;

use App\Enums\AppPlatform;
use App\Exceptions\BadRequestException;
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

    public function __construct(
        private readonly AppVersionService $appVersionService
    )
    {
    }

    /**
     * Create an app version.
     *
     * @param AppVersionRequest $request
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function create(AppVersionRequest $request): JsonResponse|JsonResource
    {
        $appVersion = $this->appVersionService->create($request->toData());

        return ResponseUtil::resource(AppVersionResource::class, $appVersion);
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
        $appVersions = $this->appVersionService->getPaginated($appVersionData);

        return ResponseUtil::resource(AppVersionResource::class, $appVersions);
    }

    /**
     * Get an app version by id.
     *
     * @param GenericRequest $request
     * @param int $appVersionId
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function getById(GenericRequest $request, int $appVersionId): JsonResponse|JsonResource
    {
        $appVersion = $this->appVersionService->getById($appVersionId, $request->getRelations());

        return ResponseUtil::resource(AppVersionResource::class, $appVersion);
    }

    /**
     * Update app version.
     *
     * @param AppVersionRequest $request
     * @param int $appVersionId
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function update(AppVersionRequest $request, int $appVersionId): JsonResponse|JsonResource
    {
        $appVersion = $this->appVersionService->update($request->toData());

        return ResponseUtil::resource(AppVersionResource::class, $appVersion);
    }

    /**
     * Delete app version.
     *
     * @param GenericRequest $request
     * @param int $appVersionId
     *
     * @return JsonResponse
     * @throws BadRequestException
     */
    public function delete(GenericRequest $request, int $appVersionId): JsonResponse
    {
        $this->appVersionService->delete($appVersionId);

        return ResponseUtil::success('App version deleted successfully.');
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
        \Log::info('Origin seen by Laravel', [
            'origin' => request()->header('Origin'),
        ]);
        $platform = $request->enum('platform', AppPlatform::class);

        if (empty($platform)) {
            return ResponseUtil::error('Platform is required.');
        }

        $appVersion = $this->appVersionService->getLatest($platform);

        return ResponseUtil::resource(AppVersionResource::class, $appVersion);
    }

}

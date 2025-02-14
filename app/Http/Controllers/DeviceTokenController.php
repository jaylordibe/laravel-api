<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenericRequest;
use App\Http\Requests\DeviceTokenRequest;
use App\Http\Resources\DeviceTokenResource;
use App\Services\DeviceTokenService;
use App\Utils\ResponseUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;

class DeviceTokenController extends Controller
{

    public function __construct(
        private readonly DeviceTokenService $deviceTokenService
    )
    {
    }

    /**
     * Create device token.
     *
     * @param DeviceTokenRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function create(DeviceTokenRequest $request): JsonResponse|JsonResource
    {
        $serviceResponse = $this->deviceTokenService->create($request->toData());

        if ($serviceResponse->failed()) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(DeviceTokenResource::class, $serviceResponse->data);
    }

    /**
     * Get paginated device tokens.
     *
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function getPaginated(GenericRequest $request): JsonResponse|JsonResource
    {
        $deviceTokenData = DeviceTokenRequest::createFrom($request)->toFilterData();
        $serviceResponse = $this->deviceTokenService->getPaginated($deviceTokenData);

        if ($serviceResponse->failed()) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(DeviceTokenResource::class, $serviceResponse->data);
    }

    /**
     * Get device token by id.
     *
     * @param GenericRequest $request
     * @param int $deviceTokenId
     *
     * @return JsonResponse|JsonResource
     */
    public function getById(GenericRequest $request, int $deviceTokenId): JsonResponse|JsonResource
    {
        $serviceResponse = $this->deviceTokenService->getById($deviceTokenId, $request->getRelations());

        if ($serviceResponse->failed()) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(DeviceTokenResource::class, $serviceResponse->data);
    }

    /**
     * Update device token.
     *
     * @param DeviceTokenRequest $request
     * @param int $deviceTokenId
     *
     * @return JsonResponse|JsonResource
     */
    public function update(DeviceTokenRequest $request, int $deviceTokenId): JsonResponse|JsonResource
    {
        $serviceResponse = $this->deviceTokenService->update($request->toData());

        if ($serviceResponse->failed()) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(DeviceTokenResource::class, $serviceResponse->data);
    }

    /**
     * Delete device token.
     *
     * @param GenericRequest $request
     * @param int $deviceTokenId
     *
     * @return JsonResponse
     */
    public function delete(GenericRequest $request, int $deviceTokenId): JsonResponse
    {
        $serviceResponse = $this->deviceTokenService->delete($deviceTokenId);

        if ($serviceResponse->failed()) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::success($serviceResponse->message);
    }

}

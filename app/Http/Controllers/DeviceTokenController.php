<?php

namespace App\Http\Controllers;

use App\Exceptions\BadRequestException;
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
     * @throws BadRequestException
     */
    public function create(DeviceTokenRequest $request): JsonResponse|JsonResource
    {
        $deviceToken = $this->deviceTokenService->create($request->toData());

        return ResponseUtil::resource(DeviceTokenResource::class, $deviceToken);
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
        $deviceTokens = $this->deviceTokenService->getPaginated($deviceTokenData);

        return ResponseUtil::resource(DeviceTokenResource::class, $deviceTokens);
    }

    /**
     * Get device token by id.
     *
     * @param GenericRequest $request
     * @param int $deviceTokenId
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function getById(GenericRequest $request, int $deviceTokenId): JsonResponse|JsonResource
    {
        $deviceToken = $this->deviceTokenService->getById($deviceTokenId, $request->getRelations());

        return ResponseUtil::resource(DeviceTokenResource::class, $deviceToken);
    }

    /**
     * Update device token.
     *
     * @param DeviceTokenRequest $request
     * @param int $deviceTokenId
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function update(DeviceTokenRequest $request, int $deviceTokenId): JsonResponse|JsonResource
    {
        $deviceToken = $this->deviceTokenService->update($request->toData());

        return ResponseUtil::resource(DeviceTokenResource::class, $deviceToken);
    }

    /**
     * Delete device token.
     *
     * @param GenericRequest $request
     * @param int $deviceTokenId
     *
     * @return JsonResponse
     * @throws BadRequestException
     */
    public function delete(GenericRequest $request, int $deviceTokenId): JsonResponse
    {
        $this->deviceTokenService->delete($deviceTokenId);

        return ResponseUtil::success('Device token deleted successfully.');
    }

}

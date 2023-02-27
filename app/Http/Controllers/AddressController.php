<?php

namespace App\Http\Controllers;

use App\Constants\GateAbilityConstant;
use App\Http\Requests\AddressRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\AddressResource;
use App\Services\AddressService;
use App\Utils\ResponseUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class AddressController extends Controller
{

    public function __construct(
        private readonly AddressService $addressService
    )
    {
    }

    /**
     * @param AddressRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function create(AddressRequest $request): JsonResponse|JsonResource
    {
        $serviceResponse = $this->addressService->create($request->toDto());

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::resource(AddressResource::class, $serviceResponse->getData());
    }

    /**
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function get(GenericRequest $request): JsonResponse|JsonResource
    {
        $addressFilterDto = AddressRequest::createFrom($request)->toFilterDto();
        $serviceResponse = $this->addressService->get($addressFilterDto);

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::resource(AddressResource::class, $serviceResponse->getData());
    }

    /**
     * @param GenericRequest $request
     * @param int $id
     *
     * @return JsonResponse|JsonResource
     */
    public function getById(GenericRequest $request, int $id): JsonResponse|JsonResource
    {
        $serviceResponse = $this->addressService->getById($id);

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::resource(AddressResource::class, $serviceResponse->getData());
    }

    /**
     * @param AddressRequest $request
     * @param int $id
     *
     * @return JsonResponse|JsonResource
     */
    public function update(AddressRequest $request, int $id): JsonResponse|JsonResource
    {
        $userDto = $request->toDto();
        $userDto->setId($id);
        $serviceResponse = $this->addressService->update($userDto);

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::resource(AddressResource::class, $serviceResponse->getData());
    }

    /**
     * @param GenericRequest $request
     * @param int $id
     *
     * @return JsonResponse
     */
    public function delete(GenericRequest $request, int $id): JsonResponse
    {
        $serviceResponse = $this->addressService->delete($id);

        if ($serviceResponse->isError()) {
            return ResponseUtil::error($serviceResponse->getMessage());
        }

        return ResponseUtil::success($serviceResponse->getMessage());
    }

}

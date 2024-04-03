<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddressRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\AddressResource;
use App\Services\AddressService;
use App\Utils\ResponseUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;

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
        $serviceResponse = $this->addressService->create($request->toData());

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(AddressResource::class, $serviceResponse->data);
    }

    /**
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function getPaginated(GenericRequest $request): JsonResponse|JsonResource
    {
        $addressFilterData = AddressRequest::createFrom($request)->toFilterData();
        $serviceResponse = $this->addressService->getPaginated($addressFilterData);

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(AddressResource::class, $serviceResponse->data);
    }

    /**
     * @param GenericRequest $request
     * @param int $addressId
     *
     * @return JsonResponse|JsonResource
     */
    public function getById(GenericRequest $request, int $addressId): JsonResponse|JsonResource
    {
        $serviceResponse = $this->addressService->getById($addressId);

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(AddressResource::class, $serviceResponse->data);
    }

    /**
     * @param AddressRequest $request
     * @param int $addressId
     *
     * @return JsonResponse|JsonResource
     */
    public function update(AddressRequest $request, int $addressId): JsonResponse|JsonResource
    {
        $serviceResponse = $this->addressService->update($request->toData());

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(AddressResource::class, $serviceResponse->data);
    }

    /**
     * @param GenericRequest $request
     * @param int $addressId
     *
     * @return JsonResponse
     */
    public function delete(GenericRequest $request, int $addressId): JsonResponse
    {
        $serviceResponse = $this->addressService->delete($addressId);

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::success($serviceResponse->message);
    }

}

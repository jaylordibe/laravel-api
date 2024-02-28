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
     * @param int $id
     *
     * @return JsonResponse|JsonResource
     */
    public function getById(GenericRequest $request, int $id): JsonResponse|JsonResource
    {
        $serviceResponse = $this->addressService->getById($id);

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(AddressResource::class, $serviceResponse->data);
    }

    /**
     * @param AddressRequest $request
     * @param int $id
     *
     * @return JsonResponse|JsonResource
     */
    public function update(AddressRequest $request, int $id): JsonResponse|JsonResource
    {
        $addressData = $request->toData();
        $addressData->id = $id;
        $serviceResponse = $this->addressService->update($addressData);

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::resource(AddressResource::class, $serviceResponse->data);
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

        if ($serviceResponse->error) {
            return ResponseUtil::error($serviceResponse->message);
        }

        return ResponseUtil::success($serviceResponse->message);
    }

}

<?php

namespace App\Http\Controllers;

use App\Exceptions\BadRequestException;
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
     * @throws BadRequestException
     */
    public function create(AddressRequest $request): JsonResponse|JsonResource
    {
        $address = $this->addressService->create($request->toData());

        return ResponseUtil::resource(AddressResource::class, $address);
    }

    /**
     * @param GenericRequest $request
     *
     * @return JsonResponse|JsonResource
     */
    public function getPaginated(GenericRequest $request): JsonResponse|JsonResource
    {
        $addressFilterData = AddressRequest::createFrom($request)->toFilterData();
        $addresses = $this->addressService->getPaginated($addressFilterData);

        return ResponseUtil::resource(AddressResource::class, $addresses);
    }

    /**
     * @param GenericRequest $request
     * @param int $addressId
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function getById(GenericRequest $request, int $addressId): JsonResponse|JsonResource
    {
        $address = $this->addressService->getById($addressId, $request->getRelations());

        return ResponseUtil::resource(AddressResource::class, $address);
    }

    /**
     * @param AddressRequest $request
     * @param int $addressId
     *
     * @return JsonResponse|JsonResource
     * @throws BadRequestException
     */
    public function update(AddressRequest $request, int $addressId): JsonResponse|JsonResource
    {
        $address = $this->addressService->update($request->toData());

        return ResponseUtil::resource(AddressResource::class, $address);
    }

    /**
     * @param GenericRequest $request
     * @param int $addressId
     *
     * @return JsonResponse
     * @throws BadRequestException
     */
    public function delete(GenericRequest $request, int $addressId): JsonResponse
    {
        $this->addressService->delete($addressId);

        return ResponseUtil::success('Address deleted successfully.');
    }

}

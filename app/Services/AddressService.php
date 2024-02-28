<?php

namespace App\Services;

use App\Data\AddressData;
use App\Data\AddressFilterData;
use App\Data\ServiceResponseData;
use App\Repositories\AddressRepository;
use App\Utils\ServiceResponseUtil;

class AddressService
{

    public function __construct(
        private readonly AddressRepository $addressRepository
    )
    {
    }

    /**
     * @param AddressData $addressData
     *
     * @return ServiceResponseData
     */
    public function create(AddressData $addressData): ServiceResponseData
    {
        $address = $this->addressRepository->save($addressData);

        if (empty($address)) {
            return ServiceResponseUtil::error('Failed to create address.');
        }

        return ServiceResponseUtil::success('Address successfully created.', $address);
    }

    /**
     * @param AddressFilterData $addressFilterData
     *
     * @return ServiceResponseData
     */
    public function getPaginated(AddressFilterData $addressFilterData): ServiceResponseData
    {
        return ServiceResponseUtil::map(
            $this->addressRepository->getPaginated($addressFilterData)
        );
    }

    /**
     * @param int $id
     * @param array $relations
     *
     * @return ServiceResponseData
     */
    public function getById(int $id, array $relations = []): ServiceResponseData
    {
        return ServiceResponseUtil::map(
            $this->addressRepository->findById($id, $relations)
        );
    }

    /**
     * @param AddressData $addressData
     *
     * @return ServiceResponseData
     */
    public function update(AddressData $addressData): ServiceResponseData
    {
        $address = $this->addressRepository->findById($addressData->id);

        if (empty($address)) {
            return ServiceResponseUtil::error('Address not found.');
        }

        $address = $this->addressRepository->save($addressData, $address);

        if (empty($address)) {
            return ServiceResponseUtil::error('Failed to update address.');
        }

        return ServiceResponseUtil::success('Address successfully updated.', $address);
    }

    /**
     * Delete address.
     *
     * @param int $id
     *
     * @return ServiceResponseData
     */
    public function delete(int $id): ServiceResponseData
    {
        $isDeleted = $this->addressRepository->delete($id);

        if (!$isDeleted) {
            return ServiceResponseUtil::error('Failed to delete address.');
        }

        return ServiceResponseUtil::success('Address successfully deleted.');
    }

}

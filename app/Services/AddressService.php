<?php

namespace App\Services;

use App\Data\AddressData;
use App\Data\AddressFilterData;
use App\Dtos\ServiceResponseDto;
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
     * @return ServiceResponseDto
     */
    public function create(AddressData $addressData): ServiceResponseDto
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
     * @return ServiceResponseDto
     */
    public function get(AddressFilterData $addressFilterData): ServiceResponseDto
    {
        return ServiceResponseUtil::map(
            $this->addressRepository->get($addressFilterData)
        );
    }

    /**
     * @param int $id
     *
     * @return ServiceResponseDto
     */
    public function getById(int $id): ServiceResponseDto
    {
        return ServiceResponseUtil::map(
            $this->addressRepository->findById($id)
        );
    }

    /**
     * @param AddressData $addressData
     *
     * @return ServiceResponseDto
     */
    public function update(AddressData $addressData): ServiceResponseDto
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
     * @return ServiceResponseDto
     */
    public function delete(int $id): ServiceResponseDto
    {
        $isDeleted = $this->addressRepository->delete($id);

        if (!$isDeleted) {
            return ServiceResponseUtil::error('Failed to delete address.');
        }

        return ServiceResponseUtil::success('Address successfully deleted.');
    }

}

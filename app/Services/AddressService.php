<?php

namespace App\Services;

use App\Dtos\AddressDto;
use App\Dtos\AddressFilterDto;
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
     * @param AddressDto $addressDto
     *
     * @return ServiceResponseDto
     */
    public function create(AddressDto $addressDto): ServiceResponseDto
    {
        $address = $this->addressRepository->save($addressDto);

        if (empty($address)) {
            return ServiceResponseUtil::error('Failed to create address.');
        }

        return ServiceResponseUtil::success('Address successfully created.', $address);
    }

    /**
     * @param AddressFilterDto $addressFilterDto
     *
     * @return ServiceResponseDto
     */
    public function get(AddressFilterDto $addressFilterDto): ServiceResponseDto
    {
        return ServiceResponseUtil::map(
            $this->addressRepository->get($addressFilterDto)
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
     * @param AddressDto $addressDto
     *
     * @return ServiceResponseDto
     */
    public function update(AddressDto $addressDto): ServiceResponseDto
    {
        $address = $this->addressRepository->findById($addressDto->getId());

        if (empty($address)) {
            return ServiceResponseUtil::error('Address not found.');
        }

        $address = $this->addressRepository->save($addressDto, $address);

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

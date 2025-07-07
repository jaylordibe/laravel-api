<?php

namespace App\Services;

use App\Data\AddressData;
use App\Data\AddressFilterData;
use App\Exceptions\BadRequestException;
use App\Models\Address;
use App\Repositories\AddressRepository;
use Illuminate\Pagination\LengthAwarePaginator;

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
     * @return Address|null
     * @throws BadRequestException
     */
    public function create(AddressData $addressData): ?Address
    {
        $address = $this->addressRepository->save($addressData);

        if (empty($address)) {
            throw new BadRequestException('Failed to create address.');
        }

        return $address;
    }

    /**
     * @param AddressFilterData $addressFilterData
     *
     * @return LengthAwarePaginator<Address>
     */
    public function getPaginated(AddressFilterData $addressFilterData): LengthAwarePaginator
    {
        return $this->addressRepository->getPaginated($addressFilterData);
    }

    /**
     * @param int $id
     * @param array $relations
     *
     * @return Address|null
     * @throws BadRequestException
     */
    public function getById(int $id, array $relations = []): ?Address
    {
        $address = $this->addressRepository->findById($id, $relations);

        if (empty($address)) {
            throw new BadRequestException('Address not found.');
        }

        return $address;
    }

    /**
     * @param AddressData $addressData
     *
     * @return Address|null
     * @throws BadRequestException
     */
    public function update(AddressData $addressData): ?Address
    {
        $address = $this->addressRepository->findById($addressData->id);

        if (empty($address)) {
            throw new BadRequestException('Address not found.');
        }

        $address = $this->addressRepository->save($addressData, $address);

        if (empty($address)) {
            throw new BadRequestException('Failed to update address.');
        }

        return $address;
    }

    /**
     * Delete address.
     *
     * @param int $id
     *
     * @return bool
     * @throws BadRequestException
     */
    public function delete(int $id): bool
    {
        $isDeleted = $this->addressRepository->delete($id);

        if (!$isDeleted) {
            throw new BadRequestException('Failed to delete address.');
        }

        return true;
    }

}

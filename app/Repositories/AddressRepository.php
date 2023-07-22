<?php

namespace App\Repositories;

use App\Dtos\AddressDto;
use App\Dtos\AddressFilterDto;
use App\Models\Address;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class AddressRepository
{

    /**
     * Find address by id.
     *
     * @param int $id
     * @param array $relations
     * @param array $columns
     *
     * @return Address|null
     */
    public function findById(int $id, array $relations = [], array $columns = ['*']): ?Address
    {
        return Address::with($relations)->where('id', $id)->first($columns);
    }

    /**
     * Save address.
     *
     * @param AddressDto $addressDto
     * @param Address|null $address
     *
     * @return Address|null
     */
    public function save(AddressDto $addressDto, ?Address $address = null): ?Address
    {
        $address ??= new Address();

        if (!$address->exists) {
            $address->user_id = $addressDto->getAuthUser()->id;
        }

        $address->address = $addressDto->getAddress();
        $address->village_or_barangay = $addressDto->getVillageOrBarangay();
        $address->city_or_municipality = $addressDto->getCityOrMunicipality();
        $address->state_or_province = $addressDto->getStateOrProvince();
        $address->zip_or_postal_code = $addressDto->getZipOrPostalCode();
        $address->country = $addressDto->getCountry();
        $address->save();

        return $address;
    }

    /**
     * @param AddressFilterDto $addressFilterDto
     *
     * @return LengthAwarePaginator
     */
    public function get(AddressFilterDto $addressFilterDto): LengthAwarePaginator
    {
        $relations = $addressFilterDto->getMeta()->getRelations();
        $sortField = $addressFilterDto->getMeta()->getSortField();
        $sortDirection = $addressFilterDto->getMeta()->getSortDirection();
        $limit = $addressFilterDto->getMeta()->getLimit();
        $addresses = Address::with($relations);

        if (!empty($addressFilterDto->getUserId())) {
            $addresses->where('user_id', $addressFilterDto->getUserId());
        }

        return $addresses->orderBy($sortField, $sortDirection)->paginate($limit);
    }

    /**
     * Delete address.
     *
     * @param int $id
     *
     * @return bool
     */
    public function delete(int $id): bool
    {
        $address = $this->findById($id);

        if (empty($address)) {
            return false;
        }

        try {
            return (bool) $address->delete();
        } catch (Exception $exception) {
            Log::error("DeleteAddressException: {$exception->getMessage()}");
        }

        return false;
    }

}

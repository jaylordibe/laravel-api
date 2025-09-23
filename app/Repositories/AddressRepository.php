<?php

namespace App\Repositories;

use App\Data\AddressData;
use App\Data\AddressFilterData;
use App\Models\Address;
use Illuminate\Pagination\LengthAwarePaginator;

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
     * @param AddressData $addressData
     * @param Address|null $address
     *
     * @return Address|null
     */
    public function save(AddressData $addressData, ?Address $address = null): ?Address
    {
        $address ??= new Address();

        if (!$address->exists) {
            $address->user_id = $addressData->userId;
        }

        $address->address = $addressData->address;
        $address->village_or_barangay = $addressData->villageOrBarangay;
        $address->city_or_municipality = $addressData->cityOrMunicipality;
        $address->state_or_province = $addressData->stateOrProvince;
        $address->zip_or_postal_code = $addressData->zipOrPostalCode;
        $address->country = $addressData->country;
        $address->save();

        return $address->refresh();
    }

    /**
     * @param AddressFilterData $addressFilterData
     *
     * @return LengthAwarePaginator<Address>
     */
    public function getPaginated(AddressFilterData $addressFilterData): LengthAwarePaginator
    {
        $addressBuilder = Address::query();

        if (!empty($addressFilterData->meta->relations)) {
            $addressBuilder->with($addressFilterData->meta->relations);
        }

        if (!empty($addressFilterData->meta->columns)) {
            $addressBuilder->select($addressFilterData->meta->columns);
        }

        if (!empty($addressFilterData->userId)) {
            $addressBuilder->where('user_id', $addressFilterData->userId);
        }

        if (!empty($addressFilterData->meta->sortField)) {
            $addressBuilder->orderBy($addressFilterData->meta->sortField, $addressFilterData->meta->sortDirection);
        }

        return $addressBuilder->paginate($addressFilterData->meta->perPage);
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
        return Address::destroy($id) > 0;
    }

}

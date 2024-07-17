<?php

namespace App\Repositories;

use App\Data\AddressData;
use App\Data\AddressFilterData;
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

        return $this->findById($address->id);
    }

    /**
     * @param AddressFilterData $addressFilterData
     *
     * @return LengthAwarePaginator
     */
    public function getPaginated(AddressFilterData $addressFilterData): LengthAwarePaginator
    {
        $addresses = Address::with($addressFilterData->meta->relations);

        if (!empty($addressFilterData->userId)) {
            $addresses->where('user_id', $addressFilterData->userId);
        }

        return $addresses->orderBy(
            $addressFilterData->meta->sortField,
            $addressFilterData->meta->sortDirection
        )->paginate($addressFilterData->meta->limit);
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

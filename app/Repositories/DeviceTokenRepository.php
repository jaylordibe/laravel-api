<?php

namespace App\Repositories;

use App\Constants\AppConstant;
use App\Data\DeviceTokenData;
use App\Data\DeviceTokenFilterData;
use App\Models\DeviceToken;
use Illuminate\Pagination\LengthAwarePaginator;

class DeviceTokenRepository
{

    /**
     * Save device token.
     *
     * @param DeviceTokenData $deviceTokenData
     * @param DeviceToken|null $deviceToken
     *
     * @return DeviceToken|null
     */
    public function save(DeviceTokenData $deviceTokenData, ?DeviceToken $deviceToken = null): ?DeviceToken
    {
        $deviceToken ??= new DeviceToken();
        $deviceToken->user_id = $deviceTokenData->userId;
        $deviceToken->token = $deviceTokenData->token;
        $deviceToken->app_platform = $deviceTokenData->appPlatform;
        $deviceToken->device_type = $deviceTokenData->deviceType;
        $deviceToken->device_os = $deviceTokenData->deviceOs;
        $deviceToken->device_os_version = $deviceTokenData->deviceOsVersion;
        $deviceToken->save();

        return $deviceToken->refresh();
    }

    /**
     * Find device token by id.
     *
     * @param int $id
     * @param array $relations
     * @param array $columns
     *
     * @return DeviceToken|null
     */
    public function findById(int $id, array $relations = [], array $columns = ['*']): ?DeviceToken
    {
        return DeviceToken::with($relations)->where('id', $id)->first($columns);
    }

    /**
     * Checks if the device token exists.
     *
     * @param int $id
     *
     * @return bool
     */
    public function exists(int $id): bool
    {
        return DeviceToken::where('id', $id)->exists();
    }

    /**
     * Get paginated device tokens.
     *
     * @param DeviceTokenFilterData $deviceTokenFilterData
     *
     * @return LengthAwarePaginator<DeviceToken>
     */
    public function getPaginated(DeviceTokenFilterData $deviceTokenFilterData): LengthAwarePaginator
    {
        $deviceTokenBuilder = DeviceToken::query();

        if (!empty($deviceTokenFilterData->meta->relations)) {
            $deviceTokenBuilder->with($deviceTokenFilterData->meta->relations);
        }

        if (!empty($deviceTokenFilterData->meta->columns)) {
            $deviceTokenBuilder->select($deviceTokenFilterData->meta->columns);
        }

        if (!empty($deviceTokenFilterData->meta->sortField)) {
            $deviceTokenBuilder->orderBy($deviceTokenFilterData->meta->sortField, $deviceTokenFilterData->meta->sortDirection ?? AppConstant::DEFAULT_SORT_DIRECTION);
        }

        return $deviceTokenBuilder->paginate($deviceTokenFilterData->meta->perPage);
    }

    /**
     * Delete device token.
     *
     * @param int $id
     *
     * @return bool
     */
    public function delete(int $id): bool
    {
        return DeviceToken::destroy($id) > 0;
    }

}

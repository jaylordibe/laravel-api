<?php

namespace App\Repositories;

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
        // The owner is set once, on create. An update never moves a device token to another user.
        if (empty($deviceToken)) {
            $deviceToken = new DeviceToken();
            $deviceToken->user_id = $deviceTokenData->userId;
        }

        $deviceToken->token = $deviceTokenData->token;
        $deviceToken->app_platform = $deviceTokenData->appPlatform;
        $deviceToken->device_type = $deviceTokenData->deviceType;
        $deviceToken->device_os = $deviceTokenData->deviceOs;
        $deviceToken->device_os_version = $deviceTokenData->deviceOsVersion;
        $deviceToken->save();

        return $deviceToken->refresh();
    }

    /**
     * Find a device token by id, among the given user's own tokens only.
     *
     * Every query in this repository is scoped to the owner: another user's token is
     * indistinguishable from one that does not exist.
     *
     * @param int $id
     * @param int $userId
     * @param array $relations
     * @param array $columns
     *
     * @return DeviceToken|null
     */
    public function findById(int $id, int $userId, array $relations = [], array $columns = ['*']): ?DeviceToken
    {
        return DeviceToken::with($relations)->where('id', $id)->where('user_id', $userId)->first($columns);
    }

    /**
     * Checks if the given user owns a device token with this id.
     *
     * @param int $id
     * @param int $userId
     *
     * @return bool
     */
    public function exists(int $id, int $userId): bool
    {
        return DeviceToken::where('id', $id)->where('user_id', $userId)->exists();
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
        // Unconditional: a missing owner matches nothing rather than everything.
        $deviceTokenBuilder = DeviceToken::query()->where('user_id', $deviceTokenFilterData->userId);

        if (!empty($deviceTokenFilterData->meta->relations)) {
            $deviceTokenBuilder->with($deviceTokenFilterData->meta->relations);
        }

        if (!empty($deviceTokenFilterData->meta->columns)) {
            $deviceTokenBuilder->select($deviceTokenFilterData->meta->columns);
        }

        if (!empty($deviceTokenFilterData->appPlatform)) {
            $deviceTokenBuilder->where('app_platform', $deviceTokenFilterData->appPlatform);
        }

        if (!empty($deviceTokenFilterData->deviceType)) {
            $deviceTokenBuilder->where('device_type', $deviceTokenFilterData->deviceType);
        }

        if (!empty($deviceTokenFilterData->deviceOs)) {
            $deviceTokenBuilder->where('device_os', $deviceTokenFilterData->deviceOs);
        }

        if (!empty($deviceTokenFilterData->meta->sortField)) {
            $deviceTokenBuilder->orderBy($deviceTokenFilterData->meta->sortField, $deviceTokenFilterData->meta->sortDirection);
        }

        return $deviceTokenBuilder->paginate($deviceTokenFilterData->meta->perPage);
    }

    /**
     * Delete one of the given user's device tokens.
     *
     * Through the model, so the soft delete and its deleted_by stamp still happen.
     *
     * @param int $id
     * @param int $userId
     *
     * @return bool
     */
    public function delete(int $id, int $userId): bool
    {
        return (bool) $this->findById($id, $userId)?->delete();
    }

}

<?php

namespace App\Repositories;

use App\Data\DeviceTokenData;
use App\Data\DeviceTokenFilterData;
use App\Models\DeviceToken;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

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

        return $deviceToken;
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
     * @return LengthAwarePaginator
     */
    public function getPaginated(DeviceTokenFilterData $deviceTokenFilterData): LengthAwarePaginator
    {
        $deviceTokens = DeviceToken::with($deviceTokenFilterData->meta->relations);

        if (!empty($deviceTokenFilterData->id)) {
            $deviceTokens->where(function (Builder $queryBuilder) use ($deviceTokenFilterData) {
                $queryBuilder->where('id', $deviceTokenFilterData->id);
            });
        }

        return $deviceTokens->orderBy(
            $deviceTokenFilterData->meta->sortField,
            $deviceTokenFilterData->meta->sortDirection
        )->paginate($deviceTokenFilterData->meta->limit);
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
        $deviceToken = $this->findById($id);

        if (empty($deviceToken)) {
            return false;
        }

        try {
            return (bool) $deviceToken->delete();
        } catch (Exception $e) {
            Log::error("Delete Device Token Exception: {$e->getMessage()}");

            return false;
        }
    }

}

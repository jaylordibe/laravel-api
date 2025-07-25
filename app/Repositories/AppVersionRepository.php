<?php

namespace App\Repositories;

use App\Constants\AppConstant;
use App\Data\AppVersionData;
use App\Data\AppVersionFilterData;
use App\Models\AppVersion;
use Illuminate\Pagination\LengthAwarePaginator;

class AppVersionRepository
{

    /**
     * Save app version.
     *
     * @param AppVersionData $appVersionData
     * @param AppVersion|null $appVersion
     *
     * @return AppVersion|null
     */
    public function save(AppVersionData $appVersionData, ?AppVersion $appVersion = null): ?AppVersion
    {
        $appVersion ??= new AppVersion();
        $appVersion->version = $appVersionData->version;
        $appVersion->description = $appVersionData->description;
        $appVersion->platform = $appVersionData->platform;
        $appVersion->release_date = $appVersionData->releaseDate;
        $appVersion->download_url = $appVersionData->downloadUrl;
        $appVersion->force_update = $appVersionData->forceUpdate;
        $appVersion->save();

        return $appVersion->refresh();
    }

    /**
     * Find app version by id.
     *
     * @param int $id
     * @param array $relations
     * @param array $columns
     *
     * @return AppVersion|null
     */
    public function findById(int $id, array $relations = [], array $columns = ['*']): ?AppVersion
    {
        return AppVersion::with($relations)->where('id', $id)->first($columns);
    }

    /**
     * Checks if the app version exists.
     *
     * @param int $id
     *
     * @return bool
     */
    public function exists(int $id): bool
    {
        return AppVersion::where('id', $id)->exists();
    }

    /**
     * Checks if the app version exists by version and platform.
     *
     * @param string $version
     * @param string $platform
     *
     * @return bool
     */
    public function existsByVersionAndPlatform(string $version, string $platform): bool
    {
        return AppVersion::where('version', $version)->where('platform', $platform)->exists();
    }

    /**
     * Get paginated app versions.
     *
     * @param AppVersionFilterData $appVersionFilterData
     *
     * @return LengthAwarePaginator<AppVersion>
     */
    public function getPaginated(AppVersionFilterData $appVersionFilterData): LengthAwarePaginator
    {
        $appVersionBuilder = AppVersion::query();

        if (!empty($appVersionFilterData->meta->relations)) {
            $appVersionBuilder->with($appVersionFilterData->meta->relations);
        }

        if (!empty($appVersionFilterData->meta->columns)) {
            $appVersionBuilder->select($appVersionFilterData->meta->columns);
        }

        if (!empty($appVersionFilterData->meta->sortField)) {
            $appVersionBuilder->orderBy($appVersionFilterData->meta->sortField, $appVersionFilterData->meta->sortDirection ?? AppConstant::DEFAULT_SORT_DIRECTION);
        }

        return $appVersionBuilder->paginate($appVersionFilterData->meta->perPage);
    }

    /**
     * Delete app version.
     *
     * @param int $id
     *
     * @return bool
     */
    public function delete(int $id): bool
    {
        return AppVersion::destroy($id) > 0;
    }

    /**
     * Get latest app version.
     *
     * @param string $platform
     *
     * @return AppVersion|null
     */
    public function getLatest(string $platform): ?AppVersion
    {
        return AppVersion::where('platform', $platform)->orderBy('release_date', 'desc')->first();
    }

}

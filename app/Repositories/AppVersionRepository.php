<?php

namespace App\Repositories;

use App\Data\AppVersionData;
use App\Data\AppVersionFilterData;
use App\Models\AppVersion;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

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

        return $this->findById($appVersion->id);
    }

    /**
     * Find app version by id.
     *
     * @param int $id
     * @param array $relations
     *
     * @return AppVersion|null
     */
    public function findById(int $id, array $relations = []): ?AppVersion
    {
        return AppVersion::with($relations)->firstWhere('id', $id);
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
     * @return LengthAwarePaginator
     */
    public function getPaginated(AppVersionFilterData $appVersionFilterData): LengthAwarePaginator
    {
        $appVersions = AppVersion::with($appVersionFilterData->meta->relations);

        if (!empty($appVersionFilterData->id)) {
            $appVersions->where(function (Builder $queryBuilder) use ($appVersionFilterData) {
                $queryBuilder->where('id', $appVersionFilterData->id);
            });
        }

        return $appVersions->orderBy(
            $appVersionFilterData->meta->sortField,
            $appVersionFilterData->meta->sortDirection
        )->paginate($appVersionFilterData->meta->limit);
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
        $appVersion = $this->findById($id);

        if (empty($appVersion)) {
            return false;
        }

        try {
            return (bool) $appVersion->delete();
        } catch (Exception $e) {
            Log::error("Delete App Version Exception: {$e->getMessage()}");

            return false;
        }
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

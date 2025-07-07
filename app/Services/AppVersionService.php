<?php

namespace App\Services;

use App\Data\AppVersionData;
use App\Data\AppVersionFilterData;
use App\Exceptions\BadRequestException;
use App\Models\AppVersion;
use App\Repositories\AppVersionRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class AppVersionService
{

    public function __construct(
        private readonly AppVersionRepository $appVersionRepository
    )
    {
    }

    /**
     * Create an app version.
     *
     * @param AppVersionData $appVersionData
     *
     * @return AppVersion|null
     * @throws BadRequestException
     */
    public function create(AppVersionData $appVersionData): ?AppVersion
    {
        $isAppVersionExists = $this->appVersionRepository->existsByVersionAndPlatform($appVersionData->version, $appVersionData->platform);

        if ($isAppVersionExists) {
            throw new BadRequestException("App version {$appVersionData->version} for platform {$appVersionData->platform} already exists.");
        }

        $appVersion = $this->appVersionRepository->save($appVersionData);

        if (empty($appVersion)) {
            throw new BadRequestException('Failed to create app version.');
        }

        return $appVersion;
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
        return $this->appVersionRepository->getPaginated($appVersionFilterData);
    }

    /**
     * Get an app version by id.
     *
     * @param int $id
     * @param array $relations
     *
     * @return AppVersion|null
     * @throws BadRequestException
     */
    public function getById(int $id, array $relations = []): ?AppVersion
    {
        $appVersion = $this->appVersionRepository->findById($id, $relations);

        if (empty($appVersion)) {
            throw new BadRequestException('App version not found.');
        }

        return $appVersion;
    }

    /**
     * Update app version.
     *
     * @param AppVersionData $appVersionData
     *
     * @return AppVersion|null
     * @throws BadRequestException
     */
    public function update(AppVersionData $appVersionData): ?AppVersion
    {
        $appVersion = $this->appVersionRepository->findById($appVersionData->id);

        if (empty($appVersion)) {
            throw new BadRequestException('Failed to update app version.');
        }

        if ($appVersionData->version !== $appVersion->version || $appVersionData->platform !== $appVersion->platform) {
            $isAppVersionExists = $this->appVersionRepository->existsByVersionAndPlatform($appVersionData->version, $appVersionData->platform);

            if ($isAppVersionExists) {
                throw new BadRequestException("App version {$appVersionData->version} for platform {$appVersionData->platform} already exists.");
            }
        }

        $appVersion = $this->appVersionRepository->save($appVersionData, $appVersion);

        if (empty($appVersion)) {
            throw new BadRequestException('Failed to update app version.');
        }

        return $appVersion;
    }

    /**
     * Delete app version.
     *
     * @param int $id
     *
     * @return bool
     * @throws BadRequestException
     */
    public function delete(int $id): bool
    {
        $isDeleted = $this->appVersionRepository->delete($id);

        if (!$isDeleted) {
            throw new BadRequestException('Failed to delete app version.');
        }

        return true;
    }

    /**
     * Get the latest app version.
     *
     * @param string $platform
     *
     * @return AppVersion|null
     */
    public function getLatest(string $platform): ?AppVersion
    {
        return $this->appVersionRepository->getLatest($platform);
    }

}

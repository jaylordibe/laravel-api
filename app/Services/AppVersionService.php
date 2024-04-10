<?php

namespace App\Services;

use App\Data\AppVersionData;
use App\Data\AppVersionFilterData;
use App\Data\ServiceResponseData;
use App\Repositories\AppVersionRepository;
use App\Utils\ServiceResponseUtil;

class AppVersionService
{

    private AppVersionRepository $appVersionRepository;

    /**
     * AppVersionService constructor.
     *
     * @param AppVersionRepository $appVersionRepository
     */
    public function __construct(AppVersionRepository $appVersionRepository)
    {
        $this->appVersionRepository = $appVersionRepository;
    }

    /**
     * Create app version.
     *
     * @param AppVersionData $appVersionData
     *
     * @return ServiceResponseData
     */
    public function create(AppVersionData $appVersionData): ServiceResponseData
    {
        $isAppVersionExists = $this->appVersionRepository->existsByVersionAndPlatform($appVersionData->version, $appVersionData->platform);

        if ($isAppVersionExists) {
            return ServiceResponseUtil::error("App version {$appVersionData->version} for platform {$appVersionData->platform} already exists.");
        }

        $appVersion = $this->appVersionRepository->save($appVersionData);

        if (empty($appVersion)) {
            return ServiceResponseUtil::error('Failed to create app version.');
        }

        return ServiceResponseUtil::success('App version successfully added.', $appVersion);
    }

    /**
     * Get paginated app versions.
     *
     * @param AppVersionFilterData $appVersionFilterData
     *
     * @return ServiceResponseData
     */
    public function getPaginated(AppVersionFilterData $appVersionFilterData): ServiceResponseData
    {
        return ServiceResponseUtil::map(
            $this->appVersionRepository->getPaginated($appVersionFilterData)
        );
    }

    /**
     * Get app version by id.
     *
     * @param int $id
     * @param array $relations
     *
     * @return ServiceResponseData
     */
    public function getById(int $id, array $relations = []): ServiceResponseData
    {
        return ServiceResponseUtil::map(
            $this->appVersionRepository->findById($id, $relations)
        );
    }

    /**
     * Update app version.
     *
     * @param AppVersionData $appVersionData
     *
     * @return ServiceResponseData
     */
    public function update(AppVersionData $appVersionData): ServiceResponseData
    {
        $appVersion = $this->appVersionRepository->findById($appVersionData->id);

        if (empty($appVersion)) {
            return ServiceResponseUtil::error('Failed to update app version.');
        }

        if ($appVersionData->version !== $appVersion->version || $appVersionData->platform !== $appVersion->platform) {
            $isAppVersionExists = $this->appVersionRepository->existsByVersionAndPlatform($appVersionData->version, $appVersionData->platform);

            if ($isAppVersionExists) {
                return ServiceResponseUtil::error("App version {$appVersionData->version} for platform {$appVersionData->platform} already exists.");
            }
        }

        $appVersion = $this->appVersionRepository->save($appVersionData, $appVersion);

        if (empty($appVersion)) {
            return ServiceResponseUtil::error('Failed to update app version.');
        }

        return ServiceResponseUtil::success('App version successfully updated.', $appVersion);
    }

    /**
     * Delete app version.
     *
     * @param int $id
     *
     * @return ServiceResponseData
     */
    public function delete(int $id): ServiceResponseData
    {
        $appVersion = $this->appVersionRepository->findById($id);

        if (empty($appVersion)) {
            return ServiceResponseUtil::error('Failed to delete app version.');
        }

        $isDeleted = $this->appVersionRepository->delete($id);

        if (!$isDeleted) {
            return ServiceResponseUtil::error('Failed to delete app version.');
        }

        return ServiceResponseUtil::success('App version successfully deleted.');
    }

    /**
     * Get the latest app version.
     *
     * @param string $platform
     *
     * @return ServiceResponseData
     */
    public function getLatest(string $platform): ServiceResponseData
    {
        return ServiceResponseUtil::map(
            $this->appVersionRepository->getLatest($platform)
        );
    }

}

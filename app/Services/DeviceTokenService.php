<?php

namespace App\Services;

use App\Data\DeviceTokenData;
use App\Data\DeviceTokenFilterData;
use App\Data\ServiceResponseData;
use App\Repositories\DeviceTokenRepository;
use App\Utils\ServiceResponseUtil;

class DeviceTokenService
{

    public function __construct(
        private readonly DeviceTokenRepository $deviceTokenRepository
    )
    {
    }

    /**
     * Create device token.
     *
     * @param DeviceTokenData $deviceTokenData
     *
     * @return ServiceResponseData
     */
    public function create(DeviceTokenData $deviceTokenData): ServiceResponseData
    {
        $deviceToken = $this->deviceTokenRepository->save($deviceTokenData);

        if (empty($deviceToken)) {
            return ServiceResponseUtil::error('Failed to create device token.');
        }

        return ServiceResponseUtil::success('Device token successfully added.', $deviceToken);
    }

    /**
     * Get paginated device tokens.
     *
     * @param DeviceTokenFilterData $deviceTokenFilterData
     *
     * @return ServiceResponseData
     */
    public function getPaginated(DeviceTokenFilterData $deviceTokenFilterData): ServiceResponseData
    {
        return ServiceResponseUtil::map(
            $this->deviceTokenRepository->getPaginated($deviceTokenFilterData)
        );
    }

    /**
     * Get device token by id.
     *
     * @param int $id
     * @param array $relations
     *
     * @return ServiceResponseData
     */
    public function getById(int $id, array $relations = []): ServiceResponseData
    {
        return ServiceResponseUtil::map(
            $this->deviceTokenRepository->findById($id, $relations)
        );
    }

    /**
     * Update device token.
     *
     * @param DeviceTokenData $deviceTokenData
     *
     * @return ServiceResponseData
     */
    public function update(DeviceTokenData $deviceTokenData): ServiceResponseData
    {
        $deviceToken = $this->deviceTokenRepository->findById($deviceTokenData->id);

        if (empty($deviceToken)) {
            return ServiceResponseUtil::error('Failed to update device token.');
        }

        $deviceToken = $this->deviceTokenRepository->save($deviceTokenData, $deviceToken);

        if (empty($deviceToken)) {
            return ServiceResponseUtil::error('Failed to update device token.');
        }

        return ServiceResponseUtil::success('Device token successfully updated.', $deviceToken);
    }

    /**
     * Delete device token.
     *
     * @param int $id
     *
     * @return ServiceResponseData
     */
    public function delete(int $id): ServiceResponseData
    {
        $deviceToken = $this->deviceTokenRepository->findById($id);

        if (empty($deviceToken)) {
            return ServiceResponseUtil::error('Failed to delete device token.');
        }

        $isDeleted = $this->deviceTokenRepository->delete($id);

        if (!$isDeleted) {
            return ServiceResponseUtil::error('Failed to delete device token.');
        }

        return ServiceResponseUtil::success('Device token successfully deleted.');
    }

}

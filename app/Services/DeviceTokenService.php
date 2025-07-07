<?php

namespace App\Services;

use App\Data\DeviceTokenData;
use App\Data\DeviceTokenFilterData;
use App\Exceptions\BadRequestException;
use App\Models\DeviceToken;
use App\Repositories\DeviceTokenRepository;
use Illuminate\Pagination\LengthAwarePaginator;

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
     * @return DeviceToken|null
     * @throws BadRequestException
     */
    public function create(DeviceTokenData $deviceTokenData): ?DeviceToken
    {
        $deviceToken = $this->deviceTokenRepository->save($deviceTokenData);

        if (empty($deviceToken)) {
            throw new BadRequestException('Failed to create device token.');
        }

        return $deviceToken;
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
        return $this->deviceTokenRepository->getPaginated($deviceTokenFilterData);
    }

    /**
     * Get device token by id.
     *
     * @param int $id
     * @param array $relations
     *
     * @return DeviceToken|null
     * @throws BadRequestException
     */
    public function getById(int $id, array $relations = []): ?DeviceToken
    {
        $deviceToken = $this->deviceTokenRepository->findById($id, $relations);

        if (empty($deviceToken)) {
            throw new BadRequestException('Device token not found.');
        }

        return $deviceToken;
    }

    /**
     * Update device token.
     *
     * @param DeviceTokenData $deviceTokenData
     *
     * @return DeviceToken|null
     * @throws BadRequestException
     */
    public function update(DeviceTokenData $deviceTokenData): ?DeviceToken
    {
        $deviceToken = $this->deviceTokenRepository->findById($deviceTokenData->id);

        if (empty($deviceToken)) {
            throw new BadRequestException('Failed to update device token.');
        }

        $deviceToken = $this->deviceTokenRepository->save($deviceTokenData, $deviceToken);

        if (empty($deviceToken)) {
            throw new BadRequestException('Failed to update device token.');
        }

        return $deviceToken;
    }

    /**
     * Delete device token.
     *
     * @param int $id
     *
     * @return bool
     * @throws BadRequestException
     */
    public function delete(int $id): bool
    {
        $deviceToken = $this->deviceTokenRepository->findById($id);

        if (empty($deviceToken)) {
            throw new BadRequestException('Failed to delete device token.');
        }

        $isDeleted = $this->deviceTokenRepository->delete($id);

        if (!$isDeleted) {
            throw new BadRequestException('Failed to delete device token.');
        }

        return true;
    }

}

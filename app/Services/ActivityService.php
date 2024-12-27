<?php

namespace App\Services;

use App\Data\ActivityData;
use App\Data\ActivityFilterData;
use App\Data\ServiceResponseData;
use App\Repositories\ActivityRepository;
use App\Utils\ServiceResponseUtil;

class ActivityService
{

    public function __construct(
        private readonly ActivityRepository $activityRepository
    )
    {
    }

    /**
     * Create activity.
     *
     * @param ActivityData $activityData
     *
     * @return ServiceResponseData
     */
    public function create(ActivityData $activityData): ServiceResponseData
    {
        $activity = activity()
            ->causedBy($activityData->userId)
            ->useLog($activityData->type)
            ->withProperties($activityData->properties)
            ->log($activityData->description);

        if (empty($activity)) {
            return ServiceResponseUtil::error('Failed to create activity.');
        }

        return ServiceResponseUtil::success('Activity successfully added.', $activity);
    }

    /**
     * Get paginated activities.
     *
     * @param ActivityFilterData $activityFilterData
     *
     * @return ServiceResponseData
     */
    public function getPaginated(ActivityFilterData $activityFilterData): ServiceResponseData
    {
        return ServiceResponseUtil::map(
            $this->activityRepository->getPaginated($activityFilterData)
        );
    }

}

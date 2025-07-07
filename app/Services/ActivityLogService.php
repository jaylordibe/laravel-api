<?php

namespace App\Services;

use App\Data\ActivityData;
use App\Data\ActivityFilterData;
use App\Exceptions\BadRequestException;
use App\Repositories\ActivityLogRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

class ActivityLogService
{

    public function __construct(
        private readonly ActivityLogRepository $activityRepository
    )
    {
    }

    /**
     * Create activity.
     *
     * @param ActivityData $activityData
     *
     * @return Activity|null
     * @throws BadRequestException
     */
    public function create(ActivityData $activityData): ?Activity
    {
        $activity = activity()
            ->causedBy($activityData->userId)
            ->useLog($activityData->logName)
            ->withProperties($activityData->properties)
            ->log($activityData->description);

        if (empty($activity)) {
            throw new BadRequestException('Failed to create activity.');
        }

        return $activity;
    }

    /**
     * Get paginated activities.
     *
     * @param ActivityFilterData $activityFilterData
     *
     * @return LengthAwarePaginator<Activity>
     */
    public function getPaginated(ActivityFilterData $activityFilterData): LengthAwarePaginator
    {
        return $this->activityRepository->getPaginated($activityFilterData);
    }

}

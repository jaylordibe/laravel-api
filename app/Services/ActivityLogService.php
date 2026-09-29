<?php

namespace App\Services;

use App\Data\ActivityData;
use App\Data\ActivityFilterData;
use App\Enums\UserPermission;
use App\Exceptions\BadRequestException;
use App\Repositories\ActivityLogRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
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
     * @throws AuthorizationException when another user's activity is requested without READ_ACTIVITY_LOG
     */
    public function getPaginated(ActivityFilterData $activityFilterData): LengthAwarePaginator
    {
        // Your own activity needs no permission; another user's does.
        if ($activityFilterData->userId !== $activityFilterData->authUser?->id) {
            Gate::authorize(UserPermission::READ_ACTIVITY_LOG);
        }

        return $this->activityRepository->getPaginated($activityFilterData);
    }

}

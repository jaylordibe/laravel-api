<?php

namespace App\Repositories;

use App\Data\ActivityFilterData;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

class ActivityLogRepository
{

    /**
     * Get paginated activities.
     *
     * @param ActivityFilterData $activityFilterData
     *
     * @return LengthAwarePaginator<Activity>
     */
    public function getPaginated(ActivityFilterData $activityFilterData): LengthAwarePaginator
    {
        $activityBuilder = Activity::query();

        if (!empty($activityFilterData->meta->relations)) {
            $activityBuilder->with($activityFilterData->meta->relations);
        }

        if (!empty($activityFilterData->id)) {
            $activityBuilder->where('id', $activityFilterData->id);
        }

        if (!empty($activityFilterData->type)) {
            $activityBuilder->where('log_name', $activityFilterData->type);
        }

        // Unconditional: a list is always one user's activity, never everyone's.
        $activityBuilder->where('causer_type', (new User())->getMorphClass())
            ->where('causer_id', $activityFilterData->userId);

        if (!empty($activityFilterData->properties)) {
            foreach ($activityFilterData->properties as $key => $value) {
                $activityBuilder->where('properties->' . $key, $value);
            }
        }

        if (!empty($activityFilterData->startDate)) {
            $activityBuilder->where('created_at', '>=', $activityFilterData->startDate);
        }

        if (!empty($activityFilterData->endDate)) {
            $activityBuilder->where('created_at', '<=', $activityFilterData->endDate);
        }

        if (!empty($activityFilterData->meta->sortField)) {
            $activityBuilder->orderBy($activityFilterData->meta->sortField, $activityFilterData->meta->sortDirection);
        }

        return $activityBuilder->paginate($activityFilterData->meta->perPage);
    }

    /**
     * Count activities by type.
     *
     * @param ActivityFilterData $activityFilterData
     *
     * @return int
     */
    public function countByFilter(ActivityFilterData $activityFilterData): int
    {
        $activities = Activity::query();

        $activities->where('causer_type', (new User())->getMorphClass())
            ->where('causer_id', $activityFilterData->userId);

        if (!empty($activityFilterData->type)) {
            $activities->where('log_name', $activityFilterData->type);
        }

        if (!empty($activityFilterData->properties)) {
            foreach ($activityFilterData->properties as $key => $value) {
                $activities->where('properties->' . $key, $value);
            }
        }

        if (!empty($activityFilterData->startDate)) {
            $activities->where('created_at', '>=', $activityFilterData->startDate);
        }

        if (!empty($activityFilterData->endDate)) {
            $activities->where('created_at', '<=', $activityFilterData->endDate);
        }

        return $activities->count();
    }

}

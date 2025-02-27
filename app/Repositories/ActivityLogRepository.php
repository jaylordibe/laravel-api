<?php

namespace App\Repositories;

use App\Constants\AppConstant;
use App\Data\ActivityFilterData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

class ActivityLogRepository
{

    /**
     * Get paginated activities.
     *
     * @param ActivityFilterData $activityFilterData
     *
     * @return LengthAwarePaginator
     */
    public function getPaginated(ActivityFilterData $activityFilterData): LengthAwarePaginator
    {
        $activityBuilder = Activity::query();

        if (!empty($activityFilterData->meta->relations)) {
            $activityBuilder->with($activityFilterData->meta->relations);
        }

        if (!empty($activityFilterData->meta->columns)) {
            $activityBuilder->select($activityFilterData->meta->columns);
        }

        if (!empty($activityFilterData->id)) {
            $activityBuilder->where('id', $activityFilterData->id);
        }

        if (!empty($activityFilterData->type)) {
            $activityBuilder->where('log_name', $activityFilterData->type);
        }

        if (!empty($activityFilterData->userId)) {
            $activityBuilder->where('causer_id', $activityFilterData->userId);
        }

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
            $activityBuilder->orderBy($activityFilterData->meta->sortField, $activityFilterData->meta->sortDirection ?? AppConstant::DEFAULT_SORT_DIRECTION);
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

        if (!empty($activityFilterData->userId)) {
            $activities->where('causer_id', $activityFilterData->userId);
        }

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

<?php

namespace App\Repositories;

use App\Data\ActivityFilterData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

class ActivityRepository
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

        if (!empty($activityFilterData->id)) {
            $activityBuilder->where('id', $activityFilterData->id);
        }

        if (!empty($activityFilterData->userId)) {
            $activityBuilder->where('causer_id', $activityFilterData->userId);
        }

        if (!empty($activityFilterData->startDate)) {
            $activityBuilder->where('created_at', '>=', $activityFilterData->startDate);
        }

        if (!empty($activityFilterData->endDate)) {
            $activityBuilder->where('created_at', '<=', $activityFilterData->endDate);
        }

        if (!empty($activityFilterData->meta->sortField)) {
            if (empty($activityFilterData->meta->sortDirection)) {
                $activityBuilder->orderBy($activityFilterData->meta->sortField);
            } else {
                $activityBuilder->orderBy($activityFilterData->meta->sortField, $activityFilterData->meta->sortDirection);
            }
        }

        return $activityBuilder->paginate($activityFilterData->meta->perPage);
    }

}

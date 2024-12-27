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
        $activities = Activity::with($activityFilterData->meta->relations);

        if (!empty($activityFilterData->id)) {
            $activities->where('id', $activityFilterData->id);
        }

        if (!empty($activityFilterData->userId)) {
            $activities->where('causer_id', $activityFilterData->userId);
        }

        if (!empty($activityFilterData->startDate)) {
            $activities->where('created_at', '>=', $activityFilterData->startDate);
        }

        if (!empty($activityFilterData->endDate)) {
            $activities->where('created_at', '<=', $activityFilterData->endDate);
        }

        return $activities->orderBy(
            $activityFilterData->meta->sortField,
            $activityFilterData->meta->sortDirection
        )->paginate($activityFilterData->meta->perPage);
    }

}

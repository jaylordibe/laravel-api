<?php

namespace App\Repositories;

use App\Constants\AppConstant;
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
            $activityBuilder->orderBy($activityFilterData->meta->sortField, $activityFilterData->meta->sortDirection ?? AppConstant::DEFAULT_SORT_DIRECTION);
        }

        return $activityBuilder->paginate($activityFilterData->meta->perPage);
    }

}

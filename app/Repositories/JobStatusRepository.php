<?php

namespace App\Repositories;

use Imtigger\LaravelJobStatus\JobStatus;

class JobStatusRepository
{

    /**
     * Find one of the given user's job statuses by id.
     *
     * @param int $id
     * @param int $userId
     * @param array $columns
     *
     * @return JobStatus|null
     */
    public function findById(int $id, int $userId, array $columns = ['*']): ?JobStatus
    {
        return JobStatus::where('id', $id)->where('user_id', $userId)->first($columns);
    }

}

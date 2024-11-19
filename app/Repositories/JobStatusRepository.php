<?php

namespace App\Repositories;

use Imtigger\LaravelJobStatus\JobStatus;

class JobStatusRepository
{

    /**
     * Find job status by id.
     *
     * @param int $id
     * @param array $columns
     *
     * @return JobStatus|null
     */
    public function findById(int $id, array $columns = ['*']): ?JobStatus
    {
        return JobStatus::where('id', $id)->first($columns);
    }

}

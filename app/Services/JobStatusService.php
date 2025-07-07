<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Repositories\JobStatusRepository;
use Imtigger\LaravelJobStatus\JobStatus;

class JobStatusService
{

    public function __construct(
        private readonly JobStatusRepository $jobStatusRepository
    )
    {
    }

    /**
     * Get job status by id.
     *
     * @param int $id
     *
     * @return JobStatus|null
     * @throws BadRequestException
     */
    public function getById(int $id): ?JobStatus
    {
        $jobStatus = $this->jobStatusRepository->findById($id);

        if (empty($jobStatus)) {
            throw new BadRequestException('Job status not found.');
        }

        return $jobStatus;
    }

}

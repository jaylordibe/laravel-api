<?php

namespace App\Services;

use App\Data\ServiceResponseData;
use App\Repositories\JobStatusRepository;
use App\Utils\ServiceResponseUtil;

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
     * @return ServiceResponseData
     */
    public function getById(int $id): ServiceResponseData
    {
        return ServiceResponseUtil::map(
            $this->jobStatusRepository->findById($id)
        );
    }

}

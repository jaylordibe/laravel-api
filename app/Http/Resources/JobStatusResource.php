<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobStatusResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'jobId' => $this->job_id,
            'type' => $this->type,
            'queue' => $this->queue,
            'attempts' => $this->attempts,
            'progressNow' => $this->progress_now,
            'progressMax' => $this->progress_max,
            'status' => $this->status,
            'input' => $this->input,
            'output' => $this->output,
            'startedAt' => $this->started_at,
            'finishedAt' => $this->finished_at,
            'progressPercentage' => $this->progress_percentage
        ];
    }

}

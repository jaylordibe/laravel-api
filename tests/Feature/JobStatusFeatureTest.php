<?php

namespace Tests\Feature;

use App\Models\User;
use Imtigger\LaravelJobStatus\JobStatus;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobStatusFeatureTest extends TestCase
{

    private string $resource = '/api/job-statuses';

    #[Test]
    public function aJobStatusIsReturnedById(): void
    {
        $admin = $this->actingAsSystemAdmin();
        $jobStatus = JobStatus::query()->create([
            'user_id' => $admin->id,
            'type' => 'App\\Jobs\\ExampleJob',
            'status' => JobStatus::STATUS_EXECUTING,
            'progress_now' => 25,
            'progress_max' => 100
        ]);

        $this->getJson("{$this->resource}/{$jobStatus->id}")
            ->assertOk()
            ->assertJson([
                'id' => $jobStatus->id,
                'status' => JobStatus::STATUS_EXECUTING,
                'progressNow' => 25,
                'progressMax' => 100
            ]);
    }

    #[Test]
    public function aMissingJobStatusIsTheStandardNotFound(): void
    {
        $this->actingAsSystemAdmin();

        $this->getJson("{$this->resource}/999999")
            ->assertBadRequest()
            ->assertExactJson(['success' => false, 'message' => 'Job status not found.']);
    }

    #[Test]
    public function aNonNumericIdIsNotRouted(): void
    {
        $this->actingAsSystemAdmin();

        $this->getJson("{$this->resource}/abc")->assertNotFound();
    }

    #[Test]
    public function anotherUsersOrAnOwnerlessJobStatusIsNotFound(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        $ownedByOther = JobStatus::query()->create(['user_id' => $owner->id, 'type' => 'App\\Jobs\\ExampleJob']);
        $ownerless = JobStatus::query()->create(['type' => 'App\\Jobs\\ExampleJob']);
        Passport::actingAs(User::factory()->create());

        foreach ([$ownedByOther, $ownerless] as $jobStatus) {
            $this->getJson("{$this->resource}/{$jobStatus->id}")
                ->assertBadRequest()
                ->assertExactJson(['success' => false, 'message' => 'Job status not found.']);
        }
    }

}

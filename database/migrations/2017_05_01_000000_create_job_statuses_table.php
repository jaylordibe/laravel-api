<?php

use App\Constants\DatabaseTableConstant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJobStatusesTable extends Migration
{

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(DatabaseTableConstant::JOB_STATUSES, function (Blueprint $table) {
            $table->increments('id');
            // Nullable: system jobs have no owner.
            $table->foreignId('user_id')->nullable()->index()->constrained(DatabaseTableConstant::USERS)->nullOnDelete();
            $table->string('job_id')->index()->nullable();
            $table->string('type')->index();
            $table->string('queue')->index()->nullable();
            $table->integer('attempts')->default(0);
            $table->integer('progress_now')->default(0);
            $table->integer('progress_max')->default(0);
            $table->string('status', 16)->default(\Imtigger\LaravelJobStatus\JobStatus::STATUS_QUEUED)->index();
            $table->longText('input')->nullable();
            $table->longText('output')->nullable();
            $table->timestamps();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop(DatabaseTableConstant::JOB_STATUSES);
    }

}

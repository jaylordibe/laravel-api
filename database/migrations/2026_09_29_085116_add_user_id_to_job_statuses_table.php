<?php

use App\Constants\DatabaseTableConstant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table(DatabaseTableConstant::JOB_STATUSES, function (Blueprint $table) {
            // Nullable: system jobs have no owner.
            $table->foreignId('user_id')->nullable()->index()->constrained(DatabaseTableConstant::USERS)->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table(DatabaseTableConstant::JOB_STATUSES, function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }

};

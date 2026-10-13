<?php

use App\Constants\DatabaseTableConstant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     *
     * Additive and nullable, so the previous build keeps working against the new schema.
     */
    public function up(): void
    {
        Schema::table(DatabaseTableConstant::USERS, function (Blueprint $table) {
            $table->string('pending_email')->nullable();
            $table->string('pending_email_token', 64)->nullable();
            $table->dateTime('pending_email_expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(DatabaseTableConstant::USERS, function (Blueprint $table) {
            $table->dropColumn(['pending_email', 'pending_email_token', 'pending_email_expires_at']);
        });
    }

};

<?php

use App\Constants\DatabaseTableConstant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(DatabaseTableConstant::USERS, function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained(DatabaseTableConstant::USERS);
            $table->foreignId('updated_by')->nullable()->constrained(DatabaseTableConstant::USERS);
            $table->foreignId('deleted_by')->nullable()->constrained(DatabaseTableConstant::USERS);
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('timezone')->nullable();
            $table->string('phone_number')->nullable();
            $table->timestamp('birthday')->nullable();
            $table->text('profile_picture')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(DatabaseTableConstant::USERS);
    }

};

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
        Schema::create(DatabaseTableConstant::DEVICE_TOKENS, function (Blueprint $table) {
            $table->id();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained(DatabaseTableConstant::USERS);
            $table->foreignId('updated_by')->nullable()->constrained(DatabaseTableConstant::USERS);
            $table->foreignId('deleted_by')->nullable()->constrained(DatabaseTableConstant::USERS);
            $table->foreignId('user_id')->index()->constrained(DatabaseTableConstant::USERS);
            $table->string('token')->index();
            $table->string('app_platform');
            $table->string('device_type');
            $table->string('device_os');
            $table->string('device_os_version');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists(DatabaseTableConstant::DEVICE_TOKENS);
    }

};

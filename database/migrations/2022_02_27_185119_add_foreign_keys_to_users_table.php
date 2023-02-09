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
    public function up() {
        Schema::table(DatabaseTableConstant::USERS, function (Blueprint $table) {
            $table->foreign('created_by')
                ->references('id')
                ->on(DatabaseTableConstant::USERS)
                ->onDelete('cascade');
            $table->foreign('updated_by')
                ->references('id')
                ->on(DatabaseTableConstant::USERS)
                ->onDelete('cascade');
            $table->foreign('deleted_by')
                ->references('id')
                ->on(DatabaseTableConstant::USERS)
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table(DatabaseTableConstant::USERS, function (Blueprint $table) {
            $table->dropForeign(['created_by', 'updated_by', 'deleted_by', 'branch_id']);
        });
    }
};

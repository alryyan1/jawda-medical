<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_default_shifts', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('shift_definition_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['user_id', 'shift_definition_id'], 'user_default_shifts_user_id_shift_definition_id_unique');
            $table->foreign('shift_definition_id', 'user_default_shifts_shift_definition_id_foreign')
                  ->references('id')
                  ->on('shifts_definitions')
                  ->onDelete('cascade');
            $table->foreign('user_id', 'user_default_shifts_user_id_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_default_shifts');
    }
};

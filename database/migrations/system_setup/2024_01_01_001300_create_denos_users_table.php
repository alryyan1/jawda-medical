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
        Schema::create('denos_users', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('shift_id');
            $table->unsignedBigInteger('deno_id');
            $table->unsignedInteger('count')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['user_id', 'shift_id', 'deno_id'], 'denos_users_user_id_shift_id_deno_id_unique');
            $table->foreign('deno_id', 'denos_users_deno_id_foreign')
                  ->references('id')
                  ->on('denos')
                  ->onDelete('cascade');
            $table->foreign('shift_id', 'denos_users_shift_id_foreign')
                  ->references('id')
                  ->on('shifts')
                  ->onDelete('cascade');
            $table->foreign('user_id', 'denos_users_user_id_foreign')
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
        Schema::dropIfExists('denos_users');
    }
};

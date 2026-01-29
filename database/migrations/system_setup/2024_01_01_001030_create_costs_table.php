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
        Schema::create('costs', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('shift_id');
            $table->unsignedBigInteger('user_cost')->nullable();
            $table->unsignedBigInteger('doctor_shift_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->string('comment', 255)->nullable();
            $table->integer('amount');
            $table->integer('doctor_shift_id_for_sub_cost')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('cost_category_id')->nullable();
            $table->integer('amount_bankak')->default(0);
            $table->unique(['doctor_shift_id', 'shift_id'], 'costs_doctor_shift_id_shift_id_unique');
            $table->foreign('doctor_shift_id', 'costs_doctor_shift_id_foreign')
                  ->references('id')
                  ->on('doctor_shifts')
                  ->onDelete('cascade');
            $table->foreign('shift_id', 'costs_shift_id_foreign')
                  ->references('id')
                  ->on('shifts')
                  ->onDelete('cascade');
            $table->foreign('user_cost', 'costs_user_cost_foreign')
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
        Schema::dropIfExists('costs');
    }
};

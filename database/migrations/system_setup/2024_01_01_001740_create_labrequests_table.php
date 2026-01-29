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
        Schema::create('labrequests', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('main_test_id');
            $table->unsignedBigInteger('pid');
            $table->integer('hidden')->default(1);
            $table->integer('is_lab2lab')->default(0);
            $table->integer('valid')->default(1);
            $table->integer('no_sample')->default(0);
            $table->string('result_status', 255)->default('pending_sample');
            $table->string('price')->default(0.0);
            $table->string('amount_paid')->default(0.0);
            $table->integer('discount_per')->default(0);
            $table->integer('is_bankak')->default(0);
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('user_requested')->nullable();
            $table->unsignedBigInteger('user_deposited')->nullable();
            $table->boolean('approve')->default(0);
            $table->double('endurance');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('is_paid')->default(0);
            $table->integer('doctor_visit_id');
            $table->unique(['main_test_id', 'pid'], 'uniqe_pid_maintest_requests');
            $table->foreign('user_deposited', 'labrequests_user_deposited_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('user_requested', 'labrequests_user_requested_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('main_test_id', 'mantest_fk_id')
                  ->references('id')
                  ->on('main_tests')
                  ->onDelete('cascade');
            $table->foreign('pid', 'pid_patients_id_fk')
                  ->references('id')
                  ->on('patients')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labrequests');
    }
};

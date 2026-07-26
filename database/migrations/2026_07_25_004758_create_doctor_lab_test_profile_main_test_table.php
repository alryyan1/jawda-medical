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
        Schema::create('doctor_lab_test_profile_main_test', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_lab_test_profile_id');
            $table->unsignedBigInteger('main_test_id');
            $table->timestamps();

            $table->foreign('doctor_lab_test_profile_id', 'dltp_main_test_profile_fk')
                ->references('id')->on('doctor_lab_test_profiles')->cascadeOnDelete();
            $table->foreign('main_test_id', 'dltp_main_test_test_fk')
                ->references('id')->on('main_tests')->cascadeOnDelete();

            $table->unique(['doctor_lab_test_profile_id', 'main_test_id'], 'dltp_main_test_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_lab_test_profile_main_test');
    }
};

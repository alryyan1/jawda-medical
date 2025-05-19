<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requested_results', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('lab_request_id');
            $table->foreign('lab_request_id')->references('id')->on('labrequests')->onDelete('cascade');

            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');

            $table->unsignedBigInteger('main_test_id');
            $table->foreign('main_test_id')->references('id')->on('main_tests')->onDelete('cascade');

            $table->unsignedBigInteger('child_test_id');
            // Add FK later if 'child_tests' table is defined:
            // $table->foreign('child_test_id')->references('id')->on('child_tests')->onDelete('cascade');

            $table->text('result')->default('');
            $table->text('normal_range'); // No default in your schema, make it nullable or add a default if appropriate
            // If normal_range can be empty or is always provided:
            // $table->text('normal_range')->default('');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requested_results');
    }
};
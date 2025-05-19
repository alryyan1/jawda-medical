<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctorvisits', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');

            $table->unsignedBigInteger('doctor_shift_id');
            $table->foreign('doctor_shift_id')->references('id')->on('doctor_shifts')->onDelete('cascade');

            $table->boolean('is_new')->default(true);
            $table->integer('number');
            $table->boolean('only_lab')->default(false);

            $table->unsignedBigInteger('shift_id');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('cascade'); // Or restrict

            $table->unsignedBigInteger('file_id');
            $table->foreign('file_id')->references('id')->on('files')->onDelete('cascade'); // Or restrict

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctorvisits');
    }
};
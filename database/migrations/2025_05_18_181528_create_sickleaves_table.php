<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sickleaves', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');

            $table->date('from_date'); // Renamed 'from' to avoid SQL keyword conflict
            $table->date('to_date');   // Renamed 'to' to avoid SQL keyword conflict
            $table->string('job_and_place_of_work')->nullable();
            $table->string('hospital_no')->nullable();
            $table->string('o_p_department')->nullable(); // Out-Patient Department

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sickleaves');
    }
};
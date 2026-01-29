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
        Schema::create('drugs_prescribed', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('item_id');
            $table->string('course', 255);
            $table->string('days', 255);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('medical_drug_route_id')->nullable();
            $table->foreign('doctor_id', 'drugs_prescribed_doctor_id_foreign')
                  ->references('id')
                  ->on('doctors')
                  ->onDelete('cascade');
            $table->foreign('item_id', 'drugs_prescribed_item_id_foreign')
                  ->references('id')
                  ->on('items')
                  ->onDelete('cascade');
            $table->foreign('patient_id', 'drugs_prescribed_patient_id_foreign')
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
        Schema::dropIfExists('drugs_prescribed');
    }
};

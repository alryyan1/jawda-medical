<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drugs_prescribed', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');

            $table->unsignedBigInteger('doctor_id');
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('restrict'); // Doctor prescribing

            $table->unsignedBigInteger('item_id'); // FK to be added once 'drugs' or 'items' table is defined
            // Example: $table->foreign('item_id')->references('id')->on('inventory_items')->onDelete('restrict');

            $table->string('course'); // Dosage instructions, e.g., "1 tablet 3 times a day"
            $table->string('days');   // Duration, e.g., "for 7 days"

            $table->unsignedBigInteger('medical_drug_route_id')->nullable(); // FK to be added later
            // Example: $table->foreign('medical_drug_route_id')->references('id')->on('medical_drug_routes')->onDelete('set null');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drugs_prescribed');
    }
};
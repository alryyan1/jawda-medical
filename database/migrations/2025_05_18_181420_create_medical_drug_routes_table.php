<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_drug_routes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., "Oral", "Intravenous", "Topical"
            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_drug_routes');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosis', function (Blueprint $table) { // Plural 'diagnoses' might be better if it's a list
            $table->id();
            $table->string('name')->unique(); // Diagnosis names/codes should be unique
            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosis');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Using 'chief_complaints' as the table name for better grammar
        Schema::create('chief_complaints', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Complaint descriptions should be unique
            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chief_complaints');
    }
};
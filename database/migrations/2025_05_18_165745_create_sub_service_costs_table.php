<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_service_costs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Cost category names should be unique
            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_service_costs');
    }
};
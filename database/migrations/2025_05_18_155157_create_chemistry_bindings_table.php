<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chemistry_bindings', function (Blueprint $table) {
            $table->id();
            $table->string('child_id_array')->nullable(); // Stores identifiers for child tests
            $table->string('name_in_mindray_table')->unique(); // Name from the Mindray machine output
            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chemistry_bindings');
    }
};
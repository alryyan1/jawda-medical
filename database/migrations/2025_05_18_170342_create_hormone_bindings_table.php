<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hormone_bindings', function (Blueprint $table) {
            $table->id();
            $table->string('child_id_array'); // Stores identifiers for child tests
            $table->string('name_in_hormone_table')->unique(); // Column name from the 'hormone' table
            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hormone_bindings');
    }
};
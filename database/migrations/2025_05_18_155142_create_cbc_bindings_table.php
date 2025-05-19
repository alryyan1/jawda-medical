<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbc_bindings', function (Blueprint $table) {
            $table->id();
            $table->string('child_id_array'); // Stores identifiers for child tests
            $table->string('name_in_sysmex_table')->unique(); // Name from the Sysmex machine output
            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbc_bindings');
    }
};
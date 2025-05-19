<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id(); // `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT
            $table->string('name', 20); // `name` varchar(20) NOT NULL
            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
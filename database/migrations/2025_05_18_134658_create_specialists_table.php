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
        Schema::create('specialists', function (Blueprint $table) {
            $table->id(); // `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT
            $table->string('name'); // `name` varchar(255) NOT NULL
            $table->timestamps(); // `created_at` and `updated_at` timestamp NULL DEFAULT NULL
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specialists');
    }
};

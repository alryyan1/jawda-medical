<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Display name of the route
            $table->string('path')->unique(); // URL path, should be unique
            $table->string('icon'); // Icon class or identifier
            $table->boolean('is_multi')->default(false); // If it's a parent route with children
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
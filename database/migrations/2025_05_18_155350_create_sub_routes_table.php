<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_routes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('route_id');
            $table->foreign('route_id')->references('id')->on('routes')->onDelete('cascade');

            $table->string('name');
            $table->string('path')->unique(); // Sub-route paths should also be unique
            $table->string('icon');
            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_routes');
    }
};
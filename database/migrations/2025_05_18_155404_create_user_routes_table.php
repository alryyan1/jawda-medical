<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_routes', function (Blueprint $table) {
            // Using combined primary key for standard pivot, or $table->id() if specific rows need direct reference
            // $table->id(); // If you want an explicit ID for this pivot row

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('route_id');
            $table->foreign('route_id')->references('id')->on('routes')->onDelete('cascade');

            $table->primary(['user_id', 'route_id']); // Standard for pivot tables
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_routes');
    }
};
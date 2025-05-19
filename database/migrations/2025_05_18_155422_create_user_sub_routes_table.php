<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sub_routes', function (Blueprint $table) {
            // $table->id(); // If you want an explicit ID for this pivot row

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('sub_route_id');
            $table->foreign('sub_route_id')->references('id')->on('sub_routes')->onDelete('cascade');

            $table->primary(['user_id', 'sub_route_id']); // Standard for pivot tables
            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sub_routes');
    }
};
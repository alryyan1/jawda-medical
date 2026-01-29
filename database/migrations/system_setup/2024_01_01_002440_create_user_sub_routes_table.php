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
        Schema::create('user_sub_routes', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('sub_route_id');
            $table->unsignedBigInteger('user_id');
            $table->foreign('sub_route_id', 'user_sub_routes_sub_route_id_foreign')
                  ->references('id')
                  ->on('sub_routes')
                  ->onDelete('cascade');
            $table->foreign('user_id', 'user_sub_routes_user_id_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sub_routes');
    }
};

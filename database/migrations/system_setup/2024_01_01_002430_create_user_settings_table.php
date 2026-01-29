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
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('user_id');
            $table->string('theme', 255)->default('light');
            $table->string('lang', 255)->default('ar');
            $table->boolean('web_dialog')->default(1);
            $table->boolean('node_dialog')->default(1);
            $table->boolean('node_direct')->default(0);
            $table->boolean('print_lab_direction')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};

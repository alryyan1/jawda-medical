<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->unique(); // Each user has one settings row
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->string('theme')->default('light');
            $table->string('lang', 10)->default('ar'); // Language codes are usually short (e.g., en, ar, fr)
            $table->boolean('web_dialog')->default(true);
            $table->boolean('node_dialog')->default(true); // Dialog preference for Node.js integration?
            $table->boolean('node_direct')->default(false); // Direct action for Node.js integration?
            $table->boolean('print_lab_direction')->default(false); // Text direction for lab printouts

            // No timestamps in original schema, but $table->timestamps() could be useful.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
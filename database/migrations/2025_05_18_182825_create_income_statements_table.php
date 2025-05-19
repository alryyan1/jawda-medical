<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_statements', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Q1 2023 Income Statement"

            $table->unsignedBigInteger('user_id'); // User who generated it
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict'); // Or set null

            $table->json('data'); // Stores the structured income statement data
            // The CHECK (json_valid(`data`)) is handled by MySQL itself if the version supports it.
            // Laravel's ->json() method creates a JSON or LONGTEXT column based on DB.

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_statements');
    }
};
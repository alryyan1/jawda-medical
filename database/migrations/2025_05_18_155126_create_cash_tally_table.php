<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_tally', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "100 SDG Note", "50 SDG Note", "1 Pound Coin"
            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_tally');
    }
};
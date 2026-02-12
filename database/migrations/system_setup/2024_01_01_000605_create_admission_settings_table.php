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
        Schema::create('admission_settings', function (Blueprint $table) {
            $table->id();

            // Time configuration for admission stay fee rules
            $table->time('morning_start')->default('07:00:00');       // 24h system start (morning)
            $table->time('morning_end')->default('12:00:00');         // 24h system end (morning)

            $table->time('evening_start')->default('13:00:00');       // full-day system start (afternoon/evening)
            $table->time('evening_end')->default('06:00:00');         // full-day system end (early next day)

            $table->time('full_day_boundary')->default('12:00:00');   // when a full day is considered complete

            $table->time('default_period_start')->default('06:00:00');// default period start
            $table->time('default_period_end')->default('07:00:00');  // default period end

            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_settings');
    }
};


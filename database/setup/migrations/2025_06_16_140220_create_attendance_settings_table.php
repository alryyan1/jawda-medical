<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('number_of_shifts_per_day')->default(2); // 1, 2, or 3
            // Add other global settings here if needed
            $table->timestamps(); // For tracking when settings were last updated
        });

        // Seed a default setting
        DB::table('attendance_settings')->insert(['number_of_shifts_per_day' => 2, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};
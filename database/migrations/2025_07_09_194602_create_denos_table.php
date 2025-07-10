<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // For seeding

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('denos', function (Blueprint $table) {
            $table->id();
            $table->integer('name')->unique(); // The denomination value, e.g., 1000, 500
            $table->integer('display_order')->default(0); // To control the display order on the frontend
            // No timestamps needed
        });

        // Seed default denominations
        DB::table('denos')->insert([
            ['name' => 1000, 'display_order' => 1],
            ['name' => 500, 'display_order' => 2],
            ['name' => 200, 'display_order' => 3],
            ['name' => 100, 'display_order' => 4],
            ['name' => 50, 'display_order' => 5],
            ['name' => 20, 'display_order' => 6],
            ['name' => 10, 'display_order' => 7],
            // Add more as needed (e.g., 5, 2, 1)
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('denos');
    }
};
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Drop existing table to ensure idempotent re-runs
        Schema::dropIfExists('denos');

        Schema::create('denos', function (Blueprint $table) {
            $table->id();
            $table->integer('name')->unique();
            $table->smallInteger('display_order')->default(0);
        });

        // Seed common denominations (adjust as needed)
        $seed = [
            ['name' => 100, 'display_order' => 1],
            ['name' => 200, 'display_order' => 2],
            ['name' => 500, 'display_order' => 3],
            ['name' => 1000, 'display_order' => 4],
        ];
        DB::table('denos')->insert($seed);
    }

    public function down(): void
    {
        Schema::dropIfExists('denos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('denos')->where('name', 2000)->exists()) {
            DB::table('denos')->insert([
                'name'          => 2000,
                'display_order' => 5,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('denos')->where('name', 2000)->delete();
    }
};

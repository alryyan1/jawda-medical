<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove small denominations no longer needed
        DB::table('denos')->whereIn('name', [10, 20, 50])->delete();

        // Re-order remaining denominations descending: 2000, 1000, 500, 200, 100
        $order = [2000 => 1, 1000 => 2, 500 => 3, 200 => 4, 100 => 5];

        foreach ($order as $name => $displayOrder) {
            DB::table('denos')->where('name', $name)->update(['display_order' => $displayOrder]);
        }
    }

    public function down(): void
    {
        // Restore small denominations and original ascending order
        $restore = [
            ['name' => 50,  'display_order' => 5],
            ['name' => 20,  'display_order' => 6],
            ['name' => 10,  'display_order' => 7],
        ];

        foreach ($restore as $deno) {
            if (!DB::table('denos')->where('name', $deno['name'])->exists()) {
                DB::table('denos')->insert($deno);
            }
        }

        // Restore ascending order
        $order = [100 => 1, 200 => 2, 500 => 3, 1000 => 4, 2000 => 5];
        foreach ($order as $name => $displayOrder) {
            DB::table('denos')->where('name', $name)->update(['display_order' => $displayOrder]);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LAB_GROUP_NAME = 'المختبر';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $exists = DB::table('service_groups')->where('name', self::LAB_GROUP_NAME)->exists();

        if ($exists) {
            return;
        }

        $maxId = (int) DB::table('service_groups')->max('id');

        DB::table('service_groups')->insert([
            'id' => $maxId + 1,
            'name' => self::LAB_GROUP_NAME,
            'is_lab' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('service_groups')
            ->where('name', self::LAB_GROUP_NAME)
            ->where('is_lab', true)
            ->delete();
    }
};

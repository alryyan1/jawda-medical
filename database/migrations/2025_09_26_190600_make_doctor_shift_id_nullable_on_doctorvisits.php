<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('doctorvisits') && Schema::hasColumn('doctorvisits', 'doctor_shift_id')) {
            // Backfill where possible to avoid FK issues
            DB::statement("UPDATE doctorvisits SET doctor_shift_id = shift_id WHERE doctor_shift_id IS NULL AND shift_id IS NOT NULL");

            // Make column nullable (avoid requiring doctrine/dbal by using raw SQL)
            DB::statement("ALTER TABLE doctorvisits MODIFY doctor_shift_id BIGINT UNSIGNED NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('doctorvisits') && Schema::hasColumn('doctorvisits', 'doctor_shift_id')) {
            // Attempt to restore NOT NULL while preserving data
            DB::statement("UPDATE doctorvisits SET doctor_shift_id = shift_id WHERE doctor_shift_id IS NULL AND shift_id IS NOT NULL");
            DB::statement("ALTER TABLE doctorvisits MODIFY doctor_shift_id BIGINT UNSIGNED NOT NULL");
        }
    }
};



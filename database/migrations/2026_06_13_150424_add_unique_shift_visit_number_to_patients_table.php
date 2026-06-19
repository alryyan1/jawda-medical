<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Abort if duplicates still exist so the constraint doesn't fail mid-migration
        $duplicates = DB::select("
            SELECT shift_id, visit_number, COUNT(*) AS cnt
            FROM patients
            GROUP BY shift_id, visit_number
            HAVING COUNT(*) > 1
        ");

        if (!empty($duplicates)) {
            $count = count($duplicates);
            throw new \RuntimeException(
                "Cannot add unique constraint: {$count} duplicate (shift_id, visit_number) group(s) exist. " .
                "Run the duplicate-fix SQL first, then re-run this migration."
            );
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->unique(['shift_id', 'visit_number'], 'patients_shift_id_visit_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique('patients_shift_id_visit_number_unique');
        });
    }
};

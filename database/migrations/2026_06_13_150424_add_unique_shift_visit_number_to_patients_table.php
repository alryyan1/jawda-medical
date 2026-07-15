<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix duplicate (shift_id, visit_number) groups before adding the unique constraint:
        // keep the earliest patient's visit_number as-is, bump the rest to
        // (current max visit_number for that shift) + 1, one at a time.
        $duplicates = DB::select("
            SELECT shift_id, visit_number, COUNT(*) AS cnt
            FROM patients
            GROUP BY shift_id, visit_number
            HAVING COUNT(*) > 1
        ");

        foreach ($duplicates as $duplicate) {
            $patientIds = DB::table('patients')
                ->where('shift_id', $duplicate->shift_id)
                ->where('visit_number', $duplicate->visit_number)
                ->orderBy('id')
                ->pluck('id');

            // Keep the first patient untouched; reassign the rest.
            $idsToReassign = $patientIds->slice(1);

            foreach ($idsToReassign as $patientId) {
                $maxVisitNumber = DB::table('patients')
                    ->where('shift_id', $duplicate->shift_id)
                    ->max('visit_number');

                DB::table('patients')
                    ->where('id', $patientId)
                    ->update(['visit_number' => $maxVisitNumber + 1]);
            }
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

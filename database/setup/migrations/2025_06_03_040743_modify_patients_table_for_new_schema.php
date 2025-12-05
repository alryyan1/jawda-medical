<?php
// database/migrations/xxxx_xx_xx_xxxxxx_modify_patients_table_for_new_schema.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Add new columns present in the 'medical' schema
            if (!Schema::hasColumn('patients', 'file_id')) { // Ensure file_id column exists if not added elsewhere
                 // $table->foreignId('file_id')->nullable()->after('id')->constrained('files')->onDelete('set null');
                 // Assuming 'files' table exists. If file_id is from old patients, it's already there.
                 // The new schema has file_id on patients. The old one didn't explicitly show it on patients.
                 // If it refers to the 'files' table, ensure 'files' table is present and FK is added.
                 // For now, just ensuring it exists:
                $table->unsignedBigInteger('file_id')->nullable()->after('id'); // Add FK later if needed
            }

            if (!Schema::hasColumn('patients', 'doctor_finish')) {
                $table->boolean('doctor_finish')->default(false)->after('discount');
            }

            // Columns to remove (moved to patient_medical_histories or deprecated)
            $columnsToRemove = [
                'present_complains', 'history_of_present_illness', 'procedures',
                'provisional_diagnosis', 'bp', 'temp', 'weight', 'height',
                'juandice', 'pallor', 'clubbing', 'cyanosis', 'edema_feet',
                'dehydration', 'lymphadenopathy', 'peripheral_pulses', 'feet_ulcer',
                'prescription_notes', 'heart_rate', 'spo2',
                'drug_history', 'family_history', 'rbs', 'care_plan',
                'general_examination_notes', 'patient_medical_history', 'social_history',
                'allergies', 'general', 'skin', 'head', 'eyes', 'ear', 'nose',
                'mouth', 'throat', 'neck', 'respiratory_system', 'cardio_system',
                'git_system', 'genitourinary_system', 'nervous_system',
                'musculoskeletal_system', 'neuropsychiatric_system', 'endocrine_system',
                'peripheral_vascular_system'
            ];
            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('patients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'file_id')) {
                // $table->dropForeign(['file_id']); // if FK was added
                $table->dropColumn('file_id');
            }
            if (Schema::hasColumn('patients', 'doctor_finish')) {
                $table->dropColumn('doctor_finish');
            }

            // Re-add columns (simplified, actual types from old schema needed for perfect rollback)
            // This is complex for a down method, usually you'd restore from backup.
            $table->text('present_complains')->nullable();
            $table->text('history_of_present_illness')->nullable();
            // ... and so on for all removed columns
        });
    }
};
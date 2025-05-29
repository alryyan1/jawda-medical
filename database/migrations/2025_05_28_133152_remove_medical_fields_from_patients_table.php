<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $columnsToRemove = [
                'present_complains', 'history_of_present_illness', 'procedures', 'provisional_diagnosis',
                'bp', 'temp', 'weight', 'height',
                'juandice', 'pallor', 'clubbing', 'cyanosis', 'edema_feet', 'dehydration',
                'lymphadenopathy', 'peripheral_pulses', 'feet_ulcer',
                'prescription_notes', 'heart_rate', 'spo2',
                'drug_history', 'family_history', 'rbs',
                // 'doctor_finish', // Keep if visit-specific, remove if general
                'care_plan',
                // 'doctor_lab_request_confirm', 'doctor_lab_urgent_confirm', // Keep if visit-specific
                'general_examination_notes', 'patient_medical_history', 'social_history', 'allergies',
                'general', 'skin', 'head', 'eyes', 'ear', 'nose', 'mouth', 'throat', 'neck',
                'respiratory_system', 'cardio_system', 'git_system', 'genitourinary_system',
                'nervous_system', 'musculoskeletal_system', 'neuropsychiatric_system',
                'endocrine_system', 'peripheral_vascular_system',
                // 'referred', // Keep if visit-specific
                // 'discount_comment', // Keep if general patient discount, remove if visit-specific
            ];
            // Before dropping, ensure you know which ones are truly patient-level history
            // and which are per-visit (and should perhaps move to doctor_visits or clinical_notes)
            // For example, 'procedures', 'provisional_diagnosis' are almost always per-visit.

            // Filter out columns that might have already been removed or don't exist
            $existingColumns = Schema::getColumnListing('patients');
            $columnsToActuallyRemove = array_intersect($columnsToRemove, $existingColumns);

            if (!empty($columnsToActuallyRemove)) {
                $table->dropColumn($columnsToActuallyRemove);
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Add columns back - types must match original
            // This is complex and error-prone; ensure you have backups.
            // Example for a few:
            $table->text('present_complains')->nullable()->after('insurance_no'); // Guessing position
            $table->text('allergies')->nullable()->after('present_complains');
            // ... add all other columns back with their original definitions and positions
            // This is why separate data migration is crucial, so you can roll back this schema change
            // without losing the data in the new table.
        });
    }
};
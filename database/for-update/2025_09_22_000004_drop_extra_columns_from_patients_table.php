<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('patients')) {
            return;
        }

        $extraColumns = [
            'present_complains',
            'history_of_present_illness',
            'procedures',
            'provisional_diagnosis',
            'bp',
            'temp',
            'weight',
            'height',
            'juandice',
            'pallor',
            'clubbing',
            'cyanosis',
            'edema_feet',
            'dehydration',
            'lymphadenopathy',
            'peripheral_pulses',
            'feet_ulcer',
            'prescription_notes',
            'heart_rate',
            'spo2',
            'drug_history',
            'family_history',
            'rbs',
            'care_plan',
            'general_examination_notes',
            'patient_medical_history',
            'social_history',
            'allergies',
            'general',
            'skin',
            'head',
            'eyes',
            'ear',
            'nose',
            'mouth',
            'throat',
            'neck',
            'respiratory_system',
            'cardio_system',
            'git_system',
            'genitourinary_system',
            'nervous_system',
            'musculoskeletal_system',
            'neuropsychiatric_system',
            'endocrine_system',
            'peripheral_vascular_system',
        ];

        // Drop each if it exists
        foreach ($extraColumns as $col) {
            if (Schema::hasColumn('patients', $col)) {
                Schema::table('patients', function (Blueprint $table) use ($col) {
                    try { $table->dropColumn($col); } catch (\Throwable $e) {}
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('patients')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            // Recreate with previous altamayoz definitions (best-effort)
            if (!Schema::hasColumn('patients', 'present_complains')) $table->text('present_complains')->nullable(false);
            if (!Schema::hasColumn('patients', 'history_of_present_illness')) $table->text('history_of_present_illness')->nullable(false);
            if (!Schema::hasColumn('patients', 'procedures')) $table->string('procedures', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'provisional_diagnosis')) $table->string('provisional_diagnosis', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'bp')) $table->string('bp', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'temp')) $table->double('temp', 8, 2)->nullable(false);
            if (!Schema::hasColumn('patients', 'weight')) $table->double('weight', 8, 2)->nullable(false);
            if (!Schema::hasColumn('patients', 'height')) $table->double('height', 8, 2)->nullable(false);
            if (!Schema::hasColumn('patients', 'juandice')) $table->boolean('juandice')->nullable();
            if (!Schema::hasColumn('patients', 'pallor')) $table->boolean('pallor')->nullable();
            if (!Schema::hasColumn('patients', 'clubbing')) $table->boolean('clubbing')->nullable();
            if (!Schema::hasColumn('patients', 'cyanosis')) $table->boolean('cyanosis')->nullable();
            if (!Schema::hasColumn('patients', 'edema_feet')) $table->boolean('edema_feet')->nullable();
            if (!Schema::hasColumn('patients', 'dehydration')) $table->boolean('dehydration')->nullable();
            if (!Schema::hasColumn('patients', 'lymphadenopathy')) $table->boolean('lymphadenopathy')->nullable();
            if (!Schema::hasColumn('patients', 'peripheral_pulses')) $table->boolean('peripheral_pulses')->nullable();
            if (!Schema::hasColumn('patients', 'feet_ulcer')) $table->boolean('feet_ulcer')->nullable();
            if (!Schema::hasColumn('patients', 'prescription_notes')) $table->string('prescription_notes', 255)->nullable();
            if (!Schema::hasColumn('patients', 'heart_rate')) $table->string('heart_rate', 255)->nullable();
            if (!Schema::hasColumn('patients', 'spo2')) $table->string('spo2', 255)->nullable();
            if (!Schema::hasColumn('patients', 'drug_history')) $table->text('drug_history')->default('')->nullable(false);
            if (!Schema::hasColumn('patients', 'family_history')) $table->text('family_history')->default('')->nullable(false);
            if (!Schema::hasColumn('patients', 'rbs')) $table->string('rbs', 255)->default('')->nullable(false);
            if (!Schema::hasColumn('patients', 'care_plan')) $table->text('care_plan')->default('')->nullable(false);
            if (!Schema::hasColumn('patients', 'general_examination_notes')) $table->string('general_examination_notes', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'patient_medical_history')) $table->string('patient_medical_history', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'social_history')) $table->string('social_history', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'allergies')) $table->string('allergies', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'general')) $table->string('general', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'skin')) $table->string('skin', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'head')) $table->string('head', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'eyes')) $table->string('eyes', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'ear')) $table->string('ear', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'nose')) $table->string('nose', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'mouth')) $table->string('mouth', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'throat')) $table->string('throat', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'neck')) $table->string('neck', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'respiratory_system')) $table->string('respiratory_system', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'cardio_system')) $table->string('cardio_system', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'git_system')) $table->string('git_system', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'genitourinary_system')) $table->string('genitourinary_system', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'nervous_system')) $table->string('nervous_system', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'musculoskeletal_system')) $table->string('musculoskeletal_system', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'neuropsychiatric_system')) $table->string('neuropsychiatric_system', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'endocrine_system')) $table->string('endocrine_system', 255)->nullable(false);
            if (!Schema::hasColumn('patients', 'peripheral_vascular_system')) $table->string('peripheral_vascular_system', 255)->nullable(false);
        });
    }
};



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
        // Drop child tables before parents to satisfy foreign key constraints.
        Schema::dropIfExists('admission_requested_service_costs');
        Schema::dropIfExists('admission_requested_service_deposits');
        Schema::dropIfExists('admission_requested_lab_tests');
        Schema::dropIfExists('admission_requested_services');
        Schema::dropIfExists('admission_vital_signs');
        Schema::dropIfExists('admission_transactions');
        Schema::dropIfExists('admission_treatments');
        Schema::dropIfExists('admission_doses');
        Schema::dropIfExists('admission_nursing_assignments');
        Schema::dropIfExists('admission_deposits');

        Schema::dropIfExists('requested_surgery_transactions');
        Schema::dropIfExists('requested_surgery_finances');
        Schema::dropIfExists('requested_surgeries');
        Schema::dropIfExists('surgical_operation_charges');
        Schema::dropIfExists('standard_surgical_charges');
        Schema::dropIfExists('surgical_operations');

        Schema::dropIfExists('admissions');
        Schema::dropIfExists('beds');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('wards');
        Schema::dropIfExists('short_stay_beds');
        Schema::dropIfExists('admission_settings');

        if (Schema::hasColumn('users', 'admission_tabs')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('admission_tabs');
            });
        }

        if (Schema::hasColumn('patients', 'specialist_doctor_id')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropForeign('patients_specialist_doctor_id_foreign');
                $table->dropColumn('specialist_doctor_id');
            });
        }

        if (Schema::hasColumn('patients', 'from_addmission_page')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropColumn('from_addmission_page');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible: the admission system and its schema have been removed from the codebase.
    }
};

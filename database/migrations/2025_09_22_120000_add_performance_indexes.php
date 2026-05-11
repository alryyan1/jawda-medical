<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // patients: speed up phone lookups, ordering, and company filtering
        Schema::table('patients', function (Blueprint $table) {
            if (! $this->indexExists('patients', 'patients_phone_index')) {
                $table->index('phone', 'patients_phone_index');
            }
            if (! $this->indexExists('patients', 'patients_phone_created_at_index')) {
                $table->index(['phone', 'created_at'], 'patients_phone_created_at_index');
            }
            if (! $this->indexExists('patients', 'patients_company_id_index')) {
                $table->index('company_id', 'patients_company_id_index');
            }
        });

        // doctorvisits: speed up patient lookups, date filters, shift reporting, and status queues
        Schema::table('doctorvisits', function (Blueprint $table) {
            if (! $this->indexExists('doctorvisits', 'doctorvisits_patient_id_index')) {
                $table->index('patient_id', 'doctorvisits_patient_id_index');
            }
            if (! $this->indexExists('doctorvisits', 'doctorvisits_patient_created_at_index')) {
                $table->index(['patient_id', 'created_at'], 'doctorvisits_patient_created_at_index');
            }
            if (! $this->indexExists('doctorvisits', 'doctorvisits_patient_visit_date_index')) {
                $table->index(['patient_id', 'visit_date'], 'doctorvisits_patient_visit_date_index');
            }
            if (! $this->indexExists('doctorvisits', 'doctorvisits_shift_id_index')) {
                $table->index('shift_id', 'doctorvisits_shift_id_index');
            }
            if (! $this->indexExists('doctorvisits', 'doctorvisits_shift_created_at_index')) {
                $table->index(['shift_id', 'created_at'], 'doctorvisits_shift_created_at_index');
            }
            if (! $this->indexExists('doctorvisits', 'doctorvisits_status_created_at_index')) {
                $table->index(['status', 'created_at'], 'doctorvisits_status_created_at_index');
            }
            if (! $this->indexExists('doctorvisits', 'doctorvisits_doctor_shift_id_index')) {
                $table->index('doctor_shift_id', 'doctorvisits_doctor_shift_id_index');
            }
        });

        // labrequests: speed up visit lookups, patient lookups, payment status
        Schema::table('labrequests', function (Blueprint $table) {
            if (! $this->indexExists('labrequests', 'labrequests_doctor_visit_id_index')) {
                $table->index('doctor_visit_id', 'labrequests_doctor_visit_id_index');
            }
            if (! $this->indexExists('labrequests', 'labrequests_pid_index')) {
                $table->index('pid', 'labrequests_pid_index');
            }
            if (! $this->indexExists('labrequests', 'labrequests_is_paid_index')) {
                $table->index('is_paid', 'labrequests_is_paid_index');
            }
            if (! $this->indexExists('labrequests', 'labrequests_is_paid_created_at_index')) {
                $table->index(['is_paid', 'created_at'], 'labrequests_is_paid_created_at_index');
            }
            if (! $this->indexExists('labrequests', 'labrequests_created_at_index')) {
                $table->index('created_at', 'labrequests_created_at_index');
            }
            if (! $this->indexExists('labrequests', 'labrequests_is_bankak_index')) {
                $table->index('is_bankak', 'labrequests_is_bankak_index');
            }
        });

        // requested_results: speed up lab request lookups and result entry patterns
        Schema::table('requested_results', function (Blueprint $table) {
            if (! $this->indexExists('requested_results', 'requested_results_lab_request_id_index')) {
                $table->index('lab_request_id', 'requested_results_lab_request_id_index');
            }
            if (! $this->indexExists('requested_results', 'requested_results_child_main_patient_index')) {
                $table->index(['child_test_id', 'main_test_id', 'patient_id'], 'requested_results_child_main_patient_index');
            }
       
         
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('patients_phone_created_at_index');
            $table->dropIndex('patients_phone_index');
            $table->dropIndex('patients_company_id_index');
        });
        Schema::table('doctorvisits', function (Blueprint $table) {
            $table->dropIndex('doctorvisits_patient_created_at_index');
            $table->dropIndex('doctorvisits_patient_visit_date_index');
            $table->dropIndex('doctorvisits_patient_id_index');
            $table->dropIndex('doctorvisits_shift_id_index');
            $table->dropIndex('doctorvisits_shift_created_at_index');
            $table->dropIndex('doctorvisits_status_created_at_index');
            $table->dropIndex('doctorvisits_doctor_shift_id_index');
        });
        Schema::table('labrequests', function (Blueprint $table) {
            $table->dropIndex('labrequests_doctor_visit_id_index');
            $table->dropIndex('labrequests_pid_index');
            $table->dropIndex('labrequests_is_paid_index');
            $table->dropIndex('labrequests_is_paid_created_at_index');
            $table->dropIndex('labrequests_created_at_index');
            $table->dropIndex('labrequests_is_bankak_index');
        });
        Schema::table('requested_results', function (Blueprint $table) {
            $table->dropIndex('requested_results_lab_request_id_index');
            $table->dropIndex('requested_results_child_main_patient_index');
            $table->dropIndex('requested_results_authorized_at_index');
            $table->dropIndex('requested_results_entered_at_index');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $connection = Schema::getConnection();
            $indexes = $connection->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        } catch (\Throwable $e) {
            // If we can't check, assume it doesn't exist to avoid blocking migration
            return false;
        }
    }
};



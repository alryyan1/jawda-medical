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
        // 1. Drop FK columns on tables that are NOT themselves being removed.
        if (Schema::hasColumn('doctors', 'sub_specialist_id')) {
            Schema::table('doctors', function (Blueprint $table) {
                $table->dropForeign('doctors_sub_specialist_id_foreign');
                $table->dropColumn('sub_specialist_id');
            });
        }

        if (Schema::hasColumn('costs', 'sub_service_cost_id')) {
            Schema::table('costs', function (Blueprint $table) {
                $table->dropForeign('costs_sub_service_cost_id_foreign');
                $table->dropColumn('sub_service_cost_id');
            });
        }

        if (Schema::hasColumn('costs', 'doctor_shift_id_for_sub_cost')) {
            Schema::table('costs', function (Blueprint $table) {
                $table->dropColumn('doctor_shift_id_for_sub_cost');
            });
        }

        // 2. Drop tables, children before parents.
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('cost_order');
        Schema::dropIfExists('requested_service_cost');
        Schema::dropIfExists('doctor_service_costs');
        Schema::dropIfExists('service_cost');
        Schema::dropIfExists('sub_specialists');
        Schema::dropIfExists('sub_service_costs');
        Schema::dropIfExists('doctor_schedules');
        Schema::dropIfExists('drugs_prescribed');
        Schema::dropIfExists('sickleaves');
        Schema::dropIfExists('requested_service_deposit_deletions');
        Schema::dropIfExists('service_price_history');
        Schema::dropIfExists('petty_cash_permissions');
        Schema::dropIfExists('medical_drug_routes');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('payment_types');
        Schema::dropIfExists('nurse_notes');
        Schema::dropIfExists('countings');
        Schema::dropIfExists('user_routes');
        Schema::dropIfExists('routes');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible: this dead scaffolding cluster and the service-cost feature
        // (and its financial-formula dependents) have been removed from the codebase.
    }
};

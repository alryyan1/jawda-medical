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
        Schema::table('admissions', function (Blueprint $table) {
            // Clinical Data
            if (!Schema::hasColumn('admissions', 'medical_history')) {
                $table->text('medical_history')->nullable()->after('operations');
            }
            if (!Schema::hasColumn('admissions', 'current_medications')) {
                $table->text('current_medications')->nullable()->after('medical_history');
            }

            // Administrative Data
            if (!Schema::hasColumn('admissions', 'referral_source')) {
                $table->string('referral_source')->nullable()->after('current_medications');
            }
            if (!Schema::hasColumn('admissions', 'expected_discharge_date')) {
                $table->date('expected_discharge_date')->nullable()->after('referral_source');
            }

            // Emergency Contact Data
            if (!Schema::hasColumn('admissions', 'next_of_kin_name')) {
                $table->string('next_of_kin_name')->nullable()->after('expected_discharge_date');
            }
            if (!Schema::hasColumn('admissions', 'next_of_kin_relation')) {
                $table->string('next_of_kin_relation')->nullable()->after('next_of_kin_name');
            }
            if (!Schema::hasColumn('admissions', 'next_of_kin_phone')) {
                $table->string('next_of_kin_phone')->nullable()->after('next_of_kin_relation');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $columnsToRemove = [
                'medical_history',
                'current_medications',
                'referral_source',
                'expected_discharge_date',
                'next_of_kin_name',
                'next_of_kin_relation',
                'next_of_kin_phone',
            ];

            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('admissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

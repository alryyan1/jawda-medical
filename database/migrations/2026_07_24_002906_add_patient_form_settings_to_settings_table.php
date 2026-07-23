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
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'require_patient_phone')) {
                $table->boolean('require_patient_phone')->default(true)->after('enforce_shift_hours');
            }
            if (! Schema::hasColumn('settings', 'show_patient_address_field')) {
                $table->boolean('show_patient_address_field')->default(true)->after('require_patient_phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'show_patient_address_field')) {
                $table->dropColumn('show_patient_address_field');
            }
            if (Schema::hasColumn('settings', 'require_patient_phone')) {
                $table->dropColumn('require_patient_phone');
            }
        });
    }
};

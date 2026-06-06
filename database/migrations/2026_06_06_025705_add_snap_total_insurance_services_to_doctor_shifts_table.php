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
        Schema::table('doctor_shifts', function (Blueprint $table) {
            $table->decimal('snap_total_insurance_services', 12, 2)->nullable()->after('snap_total_insurance_revenue');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_shifts', function (Blueprint $table) {
            $table->dropColumn('snap_total_insurance_services');
        });
    }
};

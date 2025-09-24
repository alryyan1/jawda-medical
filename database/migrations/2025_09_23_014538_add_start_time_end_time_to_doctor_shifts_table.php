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
            if (!Schema::hasColumn('doctor_shifts', 'start_time')) {
                $table->timestamp('start_time')->nullable()->after('status');
            }
            if (!Schema::hasColumn('doctor_shifts', 'end_time')) {
                $table->timestamp('end_time')->nullable()->after('start_time');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_shifts', function (Blueprint $table) {
            if (Schema::hasColumn('doctor_shifts', 'start_time')) {
                $table->dropColumn('start_time');
            }
            if (Schema::hasColumn('doctor_shifts', 'end_time')) {
                $table->dropColumn('end_time');
            }
        });
    }
};

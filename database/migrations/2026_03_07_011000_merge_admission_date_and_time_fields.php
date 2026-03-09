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
            // Change types to datetime and drop the separate time columns
            $table->dateTime('admission_date')->change();
            $table->dropColumn('admission_time');

            $table->dateTime('discharge_date')->nullable()->change();
            $table->dropColumn('discharge_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->date('admission_date')->change();
            $table->time('admission_time')->nullable()->after('admission_date');

            $table->date('discharge_date')->change();
            $table->time('discharge_time')->nullable()->after('discharge_date');
        });
    }
};

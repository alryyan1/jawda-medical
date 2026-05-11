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
        if (Schema::hasTable('labrequests') && !Schema::hasColumn('labrequests', 'doctor_visit_id')) {
            Schema::table('labrequests', function (Blueprint $table) {
                $table->unsignedBigInteger('doctor_visit_id')->nullable()->after('id');
            });

            // Optionally add a foreign key if the referenced table exists
            if (Schema::hasTable('doctor_visits')) {
                Schema::table('labrequests', function (Blueprint $table) {
                    try { $table->foreign('doctor_visit_id')->references('id')->on('doctor_visits'); } catch (\Throwable $e) {}
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('labrequests') && Schema::hasColumn('labrequests', 'doctor_visit_id')) {
            // Drop FK first if present
            Schema::table('labrequests', function (Blueprint $table) {
                try { $table->dropForeign(['doctor_visit_id']); } catch (\Throwable $e) {}
            });

            Schema::table('labrequests', function (Blueprint $table) {
                $table->dropColumn('doctor_visit_id');
            });
        }
    }
};



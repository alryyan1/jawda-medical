<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('labrequests')) {
            return;
        }

        if (!Schema::hasColumn('labrequests', 'result_status')) {
            Schema::table('labrequests', function (Blueprint $table) {
                $table->string('result_status', 255)->default('pending_sample')->after('no_sample');
            });

            try {
                DB::table('labrequests')->whereNull('result_status')->update(['result_status' => 'pending_sample']);
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('labrequests') && Schema::hasColumn('labrequests', 'result_status')) {
            Schema::table('labrequests', function (Blueprint $table) {
                try { $table->dropColumn('result_status'); } catch (\Throwable $e) {}
            });
        }
    }
};





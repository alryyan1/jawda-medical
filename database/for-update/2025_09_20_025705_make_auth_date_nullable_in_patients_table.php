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
        // First, fix invalid datetime values by setting them to the same date as print_date
        DB::statement("UPDATE patients SET auth_date = result_print_date WHERE (auth_date = '0000-00-00 00:00:00' OR auth_date = '0000-00-00') AND result_print_date IS NOT NULL");
        
        // For records where print_date is also invalid, set to current timestamp
        DB::statement("UPDATE patients SET auth_date = NOW() WHERE (auth_date = '0000-00-00 00:00:00' OR auth_date = '0000-00-00') AND (result_print_date IS NULL OR result_print_date = '0000-00-00 00:00:00' OR result_print_date = '0000-00-00')");
        
        // Now change the column to nullable
        Schema::table('patients', function (Blueprint $table) {
            $table->timestamp('auth_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->timestamp('auth_date')->nullable(false)->change();
        });
    }
};

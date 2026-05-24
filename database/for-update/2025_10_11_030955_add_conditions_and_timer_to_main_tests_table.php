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
        if (Schema::hasTable('main_tests')) {
            Schema::table('main_tests', function (Blueprint $table) {
                // Add conditions column (string/text)
                if (!Schema::hasColumn('main_tests', 'conditions')) {
                    $table->text('conditions')->nullable()->after('main_test_name');
                }
                
                // Add timer column (integer)
                if (!Schema::hasColumn('main_tests', 'timer')) {
                    $table->integer('timer')->nullable()->after('conditions');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('main_tests')) {
            Schema::table('main_tests', function (Blueprint $table) {
                // Drop the columns if they exist
                if (Schema::hasColumn('main_tests', 'conditions')) {
                    $table->dropColumn('conditions');
                }
                
                if (Schema::hasColumn('main_tests', 'timer')) {
                    $table->dropColumn('timer');
                }
            });
        }
    }
};

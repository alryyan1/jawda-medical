<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('main_tests') && ! Schema::hasColumn('main_tests', 'is_special_test')) {
            Schema::table('main_tests', function (Blueprint $table) {
                $table->boolean('is_special_test')->default(false)->after('main_test_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('main_tests') && Schema::hasColumn('main_tests', 'is_special_test')) {
            Schema::table('main_tests', function (Blueprint $table) {
                $table->dropColumn('is_special_test');
            });
        }
    }
};



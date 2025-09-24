<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('child_tests') && ! Schema::hasColumn('child_tests', 'json_params')) {
            Schema::table('child_tests', function (Blueprint $table) {
                $table->json('json_params')->nullable()->after('child_test_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('child_tests') && Schema::hasColumn('child_tests', 'json_params')) {
            Schema::table('child_tests', function (Blueprint $table) {
                $table->dropColumn('json_params');
            });
        }
    }
};



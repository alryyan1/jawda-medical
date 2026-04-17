<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('main_tests', 'allow_sorting')) {
            Schema::table('main_tests', function (Blueprint $table) {
                $table->boolean('allow_sorting')->default(false)->after('hide_unit');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('main_tests', 'allow_sorting')) {
            Schema::table('main_tests', function (Blueprint $table) {
                $table->dropColumn('allow_sorting');
            });
        }
    }
};

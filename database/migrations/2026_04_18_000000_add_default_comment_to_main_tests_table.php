<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('main_tests', function (Blueprint $table) {
            if (!Schema::hasColumn('main_tests', 'default_comment')) {
                $table->text('default_comment')->nullable()->after('allow_sorting');
            }
        });
    }

    public function down(): void
    {
        Schema::table('main_tests', function (Blueprint $table) {
            if (Schema::hasColumn('main_tests', 'default_comment')) {
                $table->dropColumn('default_comment');
            }
        });
    }
};

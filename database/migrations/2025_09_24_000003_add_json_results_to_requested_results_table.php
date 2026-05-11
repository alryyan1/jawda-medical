<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('requested_results') && ! Schema::hasColumn('requested_results', 'json_results')) {
            Schema::table('requested_results', function (Blueprint $table) {
                $table->json('json_results')->nullable()->after('result_comment');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('requested_results') && Schema::hasColumn('requested_results', 'json_results')) {
            Schema::table('requested_results', function (Blueprint $table) {
                $table->dropColumn('json_results');
            });
        }
    }
};



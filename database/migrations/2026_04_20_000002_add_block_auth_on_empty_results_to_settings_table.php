<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'block_auth_on_empty_results')) {
                $table->boolean('block_auth_on_empty_results')->default(true)->after('firebase_upload_target');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'block_auth_on_empty_results')) {
                $table->dropColumn('block_auth_on_empty_results');
            }
        });
    }
};

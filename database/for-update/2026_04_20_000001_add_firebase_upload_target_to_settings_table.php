<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'firebase_upload_target')) {
                $table->string('firebase_upload_target')->default('sales')->after('firestore_result_collection');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'firebase_upload_target')) {
                $table->dropColumn('firebase_upload_target');
            }
        });
    }
};

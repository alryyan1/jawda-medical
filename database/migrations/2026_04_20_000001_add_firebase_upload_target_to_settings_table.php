<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'firestore_result_collection')) {
                $table->string('firestore_result_collection')->nullable();
            }
            if (! Schema::hasColumn('settings', 'firebase_upload_target')) {
                $table->string('firebase_upload_target')->default('sales');
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

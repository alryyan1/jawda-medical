<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'firestore_facility_id')) {
                $table->string('firestore_facility_id')->nullable()->default('KyKrjLBHMBGHtLzU3RS3')->after('storage_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'firestore_facility_id')) {
                $table->dropColumn('firestore_facility_id');
            }
        });
    }
};

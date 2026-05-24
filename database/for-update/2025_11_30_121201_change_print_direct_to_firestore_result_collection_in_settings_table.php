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
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'print_direct')) {
                $table->dropColumn('print_direct');
            }
            if (!Schema::hasColumn('settings', 'firestore_result_collection')) {
                $table->string('firestore_result_collection')->nullable()->after('lab_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'firestore_result_collection')) {
                $table->dropColumn('firestore_result_collection');
            }
            if (!Schema::hasColumn('settings', 'print_direct')) {
                $table->boolean('print_direct')->nullable()->after('lab_name');
            }
        });
    }
};

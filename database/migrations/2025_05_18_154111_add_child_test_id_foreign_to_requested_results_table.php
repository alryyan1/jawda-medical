<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requested_results', function (Blueprint $table) {
            if (Schema::hasColumn('requested_results', 'child_test_id') && Schema::hasTable('child_tests')) {
                $table->foreign('child_test_id')
                      ->references('id')
                      ->on('child_tests')
                      ->onDelete('cascade'); // Or your desired onDelete action
            }
        });
    }

    public function down(): void
    {
        Schema::table('requested_results', function (Blueprint $table) {
            if (Schema::hasColumn('requested_results', 'child_test_id')) {
                // Convention: tablename_columnname_foreign
                $table->dropForeign(['child_test_id']); // Or $table->dropForeign('requested_results_child_test_id_foreign');
            }
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('costs', function (Blueprint $table) {
            if (!Schema::hasColumn('costs', 'user_cost')) {
                $table->unsignedBigInteger('user_cost')->nullable()->after('shift_id');
            }
        });

        // Add foreign key if not already present
        $fkExists = DB::selectOne("
            SELECT 1 AS exists_flag
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'costs'
              AND COLUMN_NAME = 'user_cost'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ");

        if (!$fkExists) {
            Schema::table('costs', function (Blueprint $table) {
                $table->foreign('user_cost')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key if it exists, then drop column
        $fkExists = DB::selectOne("
            SELECT 1 AS exists_flag
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'costs'
              AND COLUMN_NAME = 'user_cost'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ");

        if ($fkExists) {
            Schema::table('costs', function (Blueprint $table) {
                $table->dropForeign(['user_cost']);
            });
        }

        if (Schema::hasColumn('costs', 'user_cost')) {
            Schema::table('costs', function (Blueprint $table) {
                $table->dropColumn('user_cost');
            });
        }
    }
};

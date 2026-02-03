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
        Schema::table('operation_finance_items', function (Blueprint $table) {
            if (!Schema::hasColumn('operation_finance_items', 'operation_item_id')) {
                $table->unsignedBigInteger('operation_item_id')->nullable()->after('operation_id');
            }
            // Add unique constraint
            // We use a short name for the index to avoid length limits if necessary
            $table->unique(['operation_id', 'operation_item_id'], 'op_fin_items_op_id_item_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operation_finance_items', function (Blueprint $table) {
            $table->dropUnique('op_fin_items_op_id_item_id_unique');
            if (Schema::hasColumn('operation_finance_items', 'operation_item_id')) {
                $table->dropColumn('operation_item_id');
            }
        });
    }
};

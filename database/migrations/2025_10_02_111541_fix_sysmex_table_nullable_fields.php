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
        Schema::table('sysmex', function (Blueprint $table) {
            // Make existing fields nullable to allow partial data insertion
            $table->decimal('mcv', 5, 2)->nullable()->change();
            $table->decimal('mch', 5, 2)->nullable()->change();
            $table->decimal('mchc', 5, 2)->nullable()->change();
            $table->decimal('rdw_sd', 5, 2)->nullable()->change();
            $table->decimal('rdw_cv', 5, 2)->nullable()->change();
            $table->decimal('mpv', 5, 2)->nullable()->change();
            $table->decimal('pdw', 5, 2)->nullable()->change();
            $table->decimal('plcr', 5, 2)->nullable()->change();
            $table->decimal('lym_p', 5, 2)->nullable()->change();
            $table->decimal('mxd_p', 5, 2)->nullable()->change();
            $table->decimal('neut_p', 5, 2)->nullable()->change();
            $table->decimal('lym_c', 8, 3)->nullable()->change();
            $table->decimal('mxd_c', 8, 3)->nullable()->change();
            $table->decimal('neut_c', 8, 3)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sysmex', function (Blueprint $table) {
            // Revert fields to not nullable (this might fail if there are null values)
            $table->decimal('mcv', 5, 2)->nullable(false)->change();
            $table->decimal('mch', 5, 2)->nullable(false)->change();
            $table->decimal('mchc', 5, 2)->nullable(false)->change();
            $table->decimal('rdw_sd', 5, 2)->nullable(false)->change();
            $table->decimal('rdw_cv', 5, 2)->nullable(false)->change();
            $table->decimal('mpv', 5, 2)->nullable(false)->change();
            $table->decimal('pdw', 5, 2)->nullable(false)->change();
            $table->decimal('plcr', 5, 2)->nullable(false)->change();
            $table->decimal('lym_p', 5, 2)->nullable(false)->change();
            $table->decimal('mxd_p', 5, 2)->nullable(false)->change();
            $table->decimal('neut_p', 5, 2)->nullable(false)->change();
            $table->decimal('lym_c', 8, 3)->nullable(false)->change();
            $table->decimal('mxd_c', 8, 3)->nullable(false)->change();
            $table->decimal('neut_c', 8, 3)->nullable(false)->change();
        });
    }
};

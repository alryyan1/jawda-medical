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
            // BC6800 specific WBC differential parameters
            if (!Schema::hasColumn('sysmex', 'bas_c')) {
            $table->decimal('bas_c', 8, 3)->nullable()->comment('Basophils Count');
            }
            if (!Schema::hasColumn('sysmex', 'bas_p')) {
            $table->decimal('bas_p', 5, 2)->nullable()->comment('Basophils Percentage');
            }
            if (!Schema::hasColumn('sysmex', 'eos_c')) {
            $table->decimal('eos_c', 8, 3)->nullable()->comment('Eosinophils Count');
            }
            if (!Schema::hasColumn('sysmex', 'eos_p')) {
            $table->decimal('eos_p', 5, 2)->nullable()->comment('Eosinophils Percentage');
            }
            if (!Schema::hasColumn('sysmex', 'mon_c')) {
            $table->decimal('mon_c', 8, 3)->nullable()->comment('Monocytes Count');
            }
            if (!Schema::hasColumn('sysmex', 'mon_p')) {
            $table->decimal('mon_p', 5, 2)->nullable()->comment('Monocytes Percentage');
            }
            // Additional platelet parameters
            if (!Schema::hasColumn('sysmex', 'pct')) {
            $table->decimal('pct', 5, 3)->nullable()->comment('Plateletcrit');
            }
            if (!Schema::hasColumn('sysmex', 'plcc')) {
            $table->decimal('plcc', 8, 3)->nullable()->comment('Platelet Large Cell Count');
            }
            // Additional BC6800 specific parameters
            if (!Schema::hasColumn('sysmex', 'hfc_c')) {
            $table->decimal('hfc_c', 8, 3)->nullable()->comment('High Fluorescence Cell Count');
            }
            if (!Schema::hasColumn('sysmex', 'hfc_p')) {
            $table->decimal('hfc_p', 5, 2)->nullable()->comment('High Fluorescence Cell Percentage');
            }
            if (!Schema::hasColumn('sysmex', 'plt_i')) {
            $table->decimal('plt_i', 8, 3)->nullable()->comment('Platelet Immature');
            }
            if (!Schema::hasColumn('sysmex', 'wbc_d')) {
            $table->decimal('wbc_d', 8, 3)->nullable()->comment('WBC Differential');
            }
            if (!Schema::hasColumn('sysmex', 'wbc_b')) {
            $table->decimal('wbc_b', 8, 3)->nullable()->comment('WBC Basophil');
            $table->decimal('pdw_sd', 5, 2)->nullable()->comment('Platelet Distribution Width SD');
            }
            if (!Schema::hasColumn('sysmex', 'inr_c')) {
            $table->decimal('inr_c', 8, 3)->nullable()->comment('Immature Reticulocyte Count');
            }
            if (!Schema::hasColumn('sysmex', 'inr_p')) {
            $table->decimal('inr_p', 5, 2)->nullable()->comment('Immature Reticulocyte Percentage');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sysmex', function (Blueprint $table) {
            $table->dropColumn([
                'bas_c', 'bas_p', 'eos_c', 'eos_p', 'mon_c', 'mon_p',
                'pct', 'plcc', 'hfc_c', 'hfc_p', 'plt_i', 'wbc_d', 
                'wbc_b', 'pdw_sd', 'inr_c', 'inr_p'
            ]);
        });
    }
};

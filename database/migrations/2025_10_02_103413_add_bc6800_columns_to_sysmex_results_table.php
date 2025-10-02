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
            $table->decimal('bas_c', 8, 3)->nullable()->comment('Basophils Count');
            $table->decimal('bas_p', 5, 2)->nullable()->comment('Basophils Percentage');
            $table->decimal('eos_c', 8, 3)->nullable()->comment('Eosinophils Count');
            $table->decimal('eos_p', 5, 2)->nullable()->comment('Eosinophils Percentage');
            $table->decimal('mon_c', 8, 3)->nullable()->comment('Monocytes Count');
            $table->decimal('mon_p', 5, 2)->nullable()->comment('Monocytes Percentage');
            
            // Additional platelet parameters
            $table->decimal('pct', 5, 3)->nullable()->comment('Plateletcrit');
            $table->decimal('plcc', 8, 3)->nullable()->comment('Platelet Large Cell Count');
            
            // Additional BC6800 specific parameters
            $table->decimal('hfc_c', 8, 3)->nullable()->comment('High Fluorescence Cell Count');
            $table->decimal('hfc_p', 5, 2)->nullable()->comment('High Fluorescence Cell Percentage');
            $table->decimal('plt_i', 8, 3)->nullable()->comment('Platelet Immature');
            $table->decimal('wbc_d', 8, 3)->nullable()->comment('WBC Differential');
            $table->decimal('wbc_b', 8, 3)->nullable()->comment('WBC Basophil');
            $table->decimal('pdw_sd', 5, 2)->nullable()->comment('Platelet Distribution Width SD');
            $table->decimal('inr_c', 8, 3)->nullable()->comment('Immature Reticulocyte Count');
            $table->decimal('inr_p', 5, 2)->nullable()->comment('Immature Reticulocyte Percentage');
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

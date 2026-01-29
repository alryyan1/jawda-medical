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
        Schema::create('sysmex', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('doctorvisit_id');
            $table->decimal('wbc', 8, 3)->nullable();
            $table->decimal('rbc', 8, 3)->nullable();
            $table->decimal('hgb', 5, 2)->nullable();
            $table->decimal('hct', 5, 2)->nullable();
            $table->decimal('mcv', 5, 2)->nullable();
            $table->decimal('mch', 5, 2)->nullable();
            $table->decimal('mchc', 5, 2)->nullable();
            $table->decimal('plt', 8, 3)->nullable();
            $table->decimal('lym_p', 5, 2)->nullable();
            $table->decimal('mxd_p', 5, 2)->nullable();
            $table->decimal('neut_p', 5, 2)->nullable();
            $table->decimal('lym_c', 8, 3)->nullable();
            $table->decimal('mxd_c', 8, 3)->nullable();
            $table->decimal('neut_c', 8, 3)->nullable();
            $table->decimal('rdw_sd', 5, 2)->nullable();
            $table->decimal('rdw_cv', 5, 2)->nullable();
            $table->decimal('pdw', 5, 2)->nullable();
            $table->decimal('mpv', 5, 2)->nullable();
            $table->decimal('plcr', 5, 2)->nullable();
            $table->integer('flag');
            $table->decimal('mono_p', 10, 2);
            $table->string('eos_p');
            $table->string('baso_p');
            $table->string('mono_abs');
            $table->string('eso_abs');
            $table->string('baso_abs');
            $table->integer('MICROR');
            $table->decimal('bas_c', 8, 3)->nullable();
            $table->decimal('bas_p', 5, 2)->nullable();
            $table->decimal('eos_c', 8, 3)->nullable();
            $table->decimal('mon_c', 8, 3)->nullable();
            $table->decimal('mon_p', 5, 2)->nullable();
            $table->decimal('pct', 5, 3)->nullable();
            $table->decimal('plcc', 8, 3)->nullable();
            $table->decimal('hfc_c', 8, 3)->nullable();
            $table->decimal('hfc_p', 5, 2)->nullable();
            $table->decimal('plt_i', 8, 3)->nullable();
            $table->decimal('wbc_d', 8, 3)->nullable();
            $table->decimal('wbc_b', 8, 3)->nullable();
            $table->decimal('pdw_sd', 5, 2)->nullable();
            $table->decimal('inr_c', 8, 3)->nullable();
            $table->decimal('inr_p', 5, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sysmex');
    }
};

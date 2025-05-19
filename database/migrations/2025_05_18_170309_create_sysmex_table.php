<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sysmex', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('doctorvisit_id');
            $table->foreign('doctorvisit_id')->references('id')->on('doctorvisits')->onDelete('cascade');

            $table->decimal('wbc', 10, 1);
            $table->decimal('rbc', 10, 1);
            $table->decimal('hgb', 10, 1);
            $table->decimal('hct', 10, 1); // Hematocrit
            $table->integer('mcv');        // Mean Corpuscular Volume
            $table->integer('mch');        // Mean Corpuscular Hemoglobin
            $table->integer('mchc');       // Mean Corpuscular Hemoglobin Concentration
            $table->integer('plt');        // Platelet count
            $table->integer('lym_p');      // Lymphocyte percentage
            $table->decimal('mxd_p', 10, 1); // Mixed cells (Monocytes, Eosinophils, Basophils) percentage
            $table->integer('neut_p');     // Neutrophil percentage
            $table->decimal('lym_c', 10, 1); // Lymphocyte count
            $table->decimal('mxd_c', 10, 1); // Mixed cells count
            $table->decimal('neut_c', 10, 1);// Neutrophil count
            $table->decimal('rdw_sd', 10, 1);// Red Cell Distribution Width - Standard Deviation
            $table->decimal('rdw_cv', 10, 1);// Red Cell Distribution Width - Coefficient of Variation
            $table->decimal('pdw', 10, 1);   // Platelet Distribution Width
            $table->decimal('mpv', 10, 1);   // Mean Platelet Volume
            $table->decimal('plcr', 10, 1);  // Platelet Large Cell Ratio
            $table->integer('flag');       // Instrument flags or codes

            // Differential counts from some Sysmex models
            $table->decimal('mono_p', 10, 2)->nullable(); // Monocyte percentage - made nullable if not always present
            $table->decimal('eos_p', 10, 2)->nullable();  // Eosinophil percentage - schema has double, using decimal
            $table->decimal('baso_p', 10, 2)->nullable(); // Basophil percentage - schema has double, using decimal
            $table->decimal('mono_abs', 10, 2)->nullable(); // Monocyte absolute count
            $table->decimal('eso_abs', 10, 2)->nullable();  // Eosinophil absolute count
            $table->decimal('baso_abs', 10, 2)->nullable(); // Basophil absolute count
            
            $table->integer('MICROR')->nullable(); // Micro R? (Often related to microcytic RBCs or reticulocytes) - made nullable

            // No timestamps in original schema for this raw data table
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sysmex');
    }
};
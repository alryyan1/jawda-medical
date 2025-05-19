<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hormone', function (Blueprint $table) {
            // `id` int(11) NOT NULL
            $table->increments('id'); // Creates an auto-incrementing unsigned INT primary key

            // `doctorvisit_id` varchar(11) NOT NULL - This is problematic for a true FK relationship.
            // Storing as varchar as per schema. Consider changing to match doctorvisits.id type (BIGINT).
            $table->string('doctorvisit_id', 11);
            // If it were a proper FK:
            // $table->unsignedBigInteger('doctorvisit_id');
            // $table->foreign('doctorvisit_id')->references('id')->on('doctorvisits')->onDelete('cascade');

            // Most are double(10,2), will use decimal(10,2)
            // Some are varchar. These might store qualitative results (e.g., "Positive", "Negative", "<0.1")
            $table->decimal('tsh', 10, 2);
            $table->decimal('t3', 10, 2);
            $table->decimal('t4', 10, 2);
            $table->decimal('fsh', 10, 2);
            $table->decimal('lh', 10, 2);
            $table->decimal('prl', 10, 2); // Prolactin
            $table->decimal('vitd', 10, 2); // Vitamin D
            $table->decimal('pth', 10, 2); // Parathyroid Hormone
            $table->decimal('psa', 10, 2); // Prostate-Specific Antigen
            $table->decimal('fpsa', 10, 2); // Free PSA
            $table->decimal('ft3', 10, 2); // Free T3
            $table->decimal('ft4', 10, 2); // Free T4
            $table->decimal('ferr', 10, 2); // Ferritin
            $table->decimal('folate', 10, 2);
            $table->decimal('afp', 10, 2); // Alpha-fetoprotein
            $table->decimal('ca153', 10, 2); // Cancer Antigen 15-3
            $table->decimal('ca199', 10, 2); // Cancer Antigen 19-9
            $table->decimal('ca125', 10, 2); // Cancer Antigen 125
            $table->decimal('amh', 10, 2); // Anti-Müllerian Hormone
            $table->decimal('e2', 10, 2); // Estradiol
            $table->decimal('prog', 10, 2); // Progesterone
            $table->decimal('testo', 10, 2); // Testosterone
            $table->decimal('bhcg', 10, 2); // Beta-HCG
            $table->decimal('cortiso', 10, 2); // Cortisol
            $table->decimal('cea', 10, 2); // Carcinoembryonic Antigen
            $table->decimal('hiv', 10, 2); // Usually qualitative, but schema has double. Could be signal/cutoff.
            $table->decimal('antihcv', 10, 2); // Anti-HCV (Hepatitis C antibodies) - same as HIV
            $table->decimal('trop', 10, 2); // Troponin
            $table->decimal('vb12', 10, 2); // Vitamin B12
            $table->string('hbsag', 40); // Hepatitis B Surface Antigen
            $table->string('ana', 10); // Antinuclear Antibodies
            $table->string('dsdna', 10); // Anti-dsDNA
            $table->decimal('ins', 10, 1); // Insulin
            $table->decimal('cp', 10, 1); // C-peptide
            $table->string('antihbc', 10); // Anti-HBC (Hepatitis B Core antibodies)
            $table->string('Anti_HBe', 10); // Anti-HBe (Hepatitis B e-antibody) - column name has underscore
            $table->decimal('HBeAg', 10, 1); // Hepatitis B e-antigen
            $table->decimal('ccp', 10, 1); // Anti-CCP (Cyclic Citrullinated Peptide)
            $table->decimal('CK_MB', 10, 2); // Creatine Kinase-MB - column name has underscore
            $table->decimal('CMV_IgG', 10, 2);
            $table->decimal('CMV_IgM', 10, 2);
            $table->decimal('dimer', 10, 2); // D-dimer
            $table->decimal('GH', 10, 2); // Growth Hormone
            $table->decimal('HE4', 10, 2); // Human Epididymis Protein 4
            $table->decimal('HSV_IgG', 10, 2);
            $table->decimal('HSV_IgM', 10, 2);
            $table->string('IgA', 10);
            $table->string('IgE', 10);
            $table->string('IgG', 10);
            $table->string('IgM', 10);
            $table->decimal('PCT', 10, 2); // Procalcitonin
            $table->decimal('Rubella_IgG', 10, 2);
            $table->decimal('Rubella_IgM', 10, 2);
            $table->decimal('TOXO_IgG', 10, 2);
            $table->decimal('TOXO_IgM', 10, 2);
            $table->decimal('acth', 10, 2);
            $table->string('antihbs', 10); // Anti-HBs (Hepatitis B Surface antibody)

            // No timestamps in original schema for this raw data table
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hormone');
    }
};
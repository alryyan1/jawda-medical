<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mindray2', function (Blueprint $table) {
            $table->id();

            // doctorvisit_id is int(11) in schema, doctorvisits.id is BIGINT.
            // Using unsignedInteger to match int(11), but unsignedBigInteger if it must match doctorvisits.id type strictly for ORM.
            // MySQL allows INT FK to BIGINT PK. For consistency with PK type, unsignedBigInteger() is often preferred.
            $table->unsignedBigInteger('doctorvisit_id');
            // $table->unsignedInteger('doctorvisit_id'); // More precise to INT(11)
            $table->foreign('doctorvisit_id')->references('id')->on('doctorvisits')->onDelete('cascade');

            // For brevity, I'll list a few. Ensure all columns from your schema are here.
            // Numeric values should ideally be nullable if a result might be missing.
            // The schema specifies NOT NULL for many, which means a value must always be present.
            $table->decimal('pho', 10, 1); // Changed double to decimal
            $table->decimal('mg', 10, 2);
            $table->decimal('ca', 10, 2);
            $table->integer('gluh'); // Glucose (urine/hemoglobin?)
            $table->decimal('tb', 10, 1); // Total Bilirubin
            $table->decimal('db', 10, 1); // Direct Bilirubin
            $table->integer('alt');
            $table->integer('ast');
            $table->decimal('crp', 10, 1);
            $table->integer('alp');
            $table->decimal('tp', 10, 1);  // Total Protein
            $table->decimal('alb', 10, 1); // Albumin
            $table->integer('tg');  // Triglycerides
            $table->integer('ldl');
            $table->integer('hdl');
            $table->integer('tc');  // Total Cholesterol
            $table->decimal('crea', 10, 1); // Creatinine
            $table->decimal('uric', 10, 1); // Uric Acid
            $table->integer('urea');
            $table->decimal('ckmb', 10, 2);
            $table->decimal('ck', 10, 2);
            $table->decimal('ldh', 10, 2);
            $table->decimal('fe', 10, 2); // Iron
            $table->decimal('fer', 10, 2); // Ferritin
            $table->integer('glug'); // Glucose (general?)
            $table->decimal('ddimer', 10, 2);
            $table->decimal('amylase', 10, 2);
            $table->decimal('lipase', 10, 2);
            $table->integer('aso');
            $table->decimal('tibc', 10, 2);
            $table->decimal('acr', 10, 2); // Albumin/Creatinine Ratio
            $table->decimal('pcr', 10, 2); // Protein/Creatinine Ratio
            $table->decimal('hb', 10, 2);  // Hemoglobin (already decimal in schema)
            $table->decimal('na', 10, 2);  // Sodium
            $table->decimal('k', 10, 2);   // Potassium
            $table->decimal('c1', 10, 2);  // Chloride?
            $table->decimal('c2', 10, 2);  // Calcium (ionized?) or another param
            $table->string('ggt', 200);
            $table->string('a1c', 200); // HbA1c
            $table->string('iron', 200); // Iron (string? fe and fer are decimal)
            $table->string('tpc3', 200); // ???

            // No timestamps in original schema for this raw data table
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mindray2');
    }
};
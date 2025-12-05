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
        Schema::table('patients', function (Blueprint $table) {
            $table->string('general');
            $table->string('skin');
            $table->string('head');
            $table->string('eyes');
            $table->string('ear');
            $table->string('nose');
            $table->string('mouth');
            $table->string('throat');
            $table->string('neck');
            $table->string('respiratory_system');
            $table->string('cardio_system');
            $table->string('git_system');
            $table->string('genitourinary_system');
            $table->string('nervous_system');
            $table->string('musculoskeletal_system');
            $table->string('neuropsychiatric_system');
            $table->string('endocrine_system');
            $table->string('peripheral_vascular_system');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            //
        });
    }
};

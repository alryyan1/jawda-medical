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
        Schema::create('admission_vital_signs', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('admission_id');
            $table->unsignedBigInteger('user_id');
            $table->date('reading_date');
            $table->time('reading_time');
            $table->decimal('temperature', 5, 2)->nullable();
            $table->integer('blood_pressure_systolic')->nullable();
            $table->integer('blood_pressure_diastolic')->nullable();
            $table->decimal('oxygen_saturation', 5, 2)->nullable();
            $table->decimal('oxygen_flow', 5, 2)->nullable();
            $table->integer('pulse_rate')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('admission_id', 'admission_vital_signs_admission_id_foreign')
                  ->references('id')
                  ->on('admissions')
                  ->onDelete('cascade');
            $table->foreign('user_id', 'admission_vital_signs_user_id_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_vital_signs');
    }
};

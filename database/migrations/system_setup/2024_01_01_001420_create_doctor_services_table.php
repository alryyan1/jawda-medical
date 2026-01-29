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
        Schema::create('doctor_services', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('service_id');
            $table->string('percentage');
            $table->string('fixed');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['doctor_id', 'service_id'], 'doctor_services_doctor_id_service_id_unique');
            $table->foreign('doctor_id', 'doctor_services_doctor_id_foreign')
                  ->references('id')
                  ->on('doctors')
                  ->onDelete('cascade');
            $table->foreign('service_id', 'doctor_services_service_id_foreign')
                  ->references('id')
                  ->on('services')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_services');
    }
};

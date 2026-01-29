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
        Schema::create('requested_services', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('doctorvisits_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('user_deposited')->nullable();
            $table->unsignedBigInteger('doctor_id');
            $table->string('price');
            $table->string('amount_paid');
            $table->string('endurance');
            $table->boolean('is_paid');
            $table->integer('discount');
            $table->boolean('bank');
            $table->integer('count');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('doctor_note', 255)->default('');
            $table->string('nurse_note', 255)->default('');
            $table->boolean('done')->default(0);
            $table->boolean('approval')->default(0);
            $table->integer('discount_per');
            $table->unique(['doctorvisits_id', 'service_id'], 'requested_services_doctorvisits_id_service_id_unique');
            $table->foreign('doctor_id', 'requested_services_doctor_id_foreign')
                  ->references('id')
                  ->on('doctors')
                  ->onDelete('cascade');
            $table->foreign('doctorvisits_id', 'requested_services_doctorvisits_id_foreign')
                  ->references('id')
                  ->on('doctorvisits')
                  ->onDelete('cascade');
            $table->foreign('service_id', 'requested_services_service_id_foreign')
                  ->references('id')
                  ->on('services')
                  ->onDelete('cascade');
            $table->foreign('user_deposited', 'requested_services_user_deposited_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('user_id', 'requested_services_user_id_foreign')
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
        Schema::dropIfExists('requested_services');
    }
};

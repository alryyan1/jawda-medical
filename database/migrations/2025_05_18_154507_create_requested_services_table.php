<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requested_services', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('doctorvisits_id'); // Keeping as per schema, consider 'doctor_visit_id'
            $table->foreign('doctorvisits_id')->references('id')->on('doctorvisits')->onDelete('cascade');

            $table->unsignedBigInteger('service_id');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('restrict'); // Or cascade

            $table->unsignedBigInteger('user_id'); // User who created the request
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');

            $table->unsignedBigInteger('user_deposited')->nullable(); // User who handled payment
            $table->foreign('user_deposited')->references('id')->on('users')->onDelete('set null');

            $table->unsignedBigInteger('doctor_id'); // Doctor associated with the service
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('restrict');

            $table->decimal('price', 11, 2); // Changed from bigint for consistency with amount_paid
            $table->decimal('amount_paid', 11, 2);
            $table->decimal('endurance', 11, 2);
            $table->boolean('is_paid'); // No default in schema
            $table->decimal('discount', 11, 2)->default(0); // Changed from int, added default
            $table->boolean('bank'); // 'is_bank_payment'? No default in schema
            $table->integer('count');
            $table->string('doctor_note')->default('');
            $table->string('nurse_note')->default('');
            $table->boolean('done')->default(false);
            $table->boolean('approval')->default(false);
            $table->integer('discount_per')->default(0); // discount_percentage

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requested_services');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requested_service_deposits', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('requested_service_id');
            $table->foreign('requested_service_id')->references('id')->on('requested_services')->onDelete('cascade');

            $table->decimal('amount', 15, 2); // Changed from bigint to decimal

            $table->unsignedBigInteger('user_id'); // User processing deposit
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');

            $table->boolean('is_bank');
            $table->boolean('is_claimed')->default(false); // Added default

            $table->unsignedBigInteger('shift_id');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('restrict');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requested_service_deposits');
    }
};
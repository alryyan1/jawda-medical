<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requested_service_cost', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('requested_service_id');
            $table->foreign('requested_service_id')->references('id')->on('requested_services')->onDelete('cascade');

            $table->unsignedBigInteger('sub_service_cost_id');
            $table->foreign('sub_service_cost_id')->references('id')->on('sub_service_costs')->onDelete('cascade'); // Or restrict

            $table->unsignedBigInteger('service_cost_id');
            $table->foreign('service_cost_id')->references('id')->on('service_cost')->onDelete('cascade'); // Or restrict

            $table->decimal('amount', 15, 2); // Changed from bigint to decimal

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requested_service_cost');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_cost', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            $table->unsignedBigInteger('service_id');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');

            $table->decimal('percentage', 8, 2);
            $table->decimal('fixed', 11, 2);
            $table->enum('cost_type', ['total', 'after cost'])->default('total');

            $table->unsignedBigInteger('sub_service_cost_id');
            $table->foreign('sub_service_cost_id')->references('id')->on('sub_service_costs')->onDelete('cascade'); // Or restrict

            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_cost');
    }
};
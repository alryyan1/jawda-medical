<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_order', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('service_id');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');

            $table->unsignedBigInteger('service_cost_id')->nullable();
            $table->foreign('service_cost_id')->references('id')->on('service_cost')->onDelete('set null'); // Or cascade/restrict

            $table->unsignedBigInteger('sub_service_cost_id');
            $table->foreign('sub_service_cost_id')->references('id')->on('sub_service_costs')->onDelete('cascade'); // Or restrict

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_order');
    }
};
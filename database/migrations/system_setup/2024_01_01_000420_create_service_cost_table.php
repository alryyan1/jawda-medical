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
        Schema::create('service_cost', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('sub_service_cost_id');
            $table->unsignedBigInteger('service_id');
            $table->string('percentage')->default(0.00);
            $table->string('fixed');
            $table->enum('cost_type', ["total","after cost"])->default('total');
            $table->foreign('service_id', 'service_cost_service_id_foreign')
                  ->references('id')
                  ->on('services')
                  ->onDelete('cascade');
            $table->foreign('sub_service_cost_id', 'service_cost_sub_service_cost_id_foreign')
                  ->references('id')
                  ->on('sub_service_costs')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_cost');
    }
};

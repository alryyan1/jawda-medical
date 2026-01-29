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
        Schema::create('admission_requested_service_costs', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('admission_requested_service_id');
            $table->unsignedBigInteger('service_cost_id');
            $table->unsignedBigInteger('sub_service_cost_id')->nullable();
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('service_cost_id', 'admission_requested_service_costs_service_cost_id_foreign')
                  ->references('id')
                  ->on('service_cost')
                  ->onDelete('cascade');
            $table->foreign('sub_service_cost_id', 'admission_requested_service_costs_sub_service_cost_id_foreign')
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
        Schema::dropIfExists('admission_requested_service_costs');
    }
};

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
        Schema::create('requested_service_cost', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('requested_service_id');
            $table->unsignedBigInteger('sub_service_cost_id');
            $table->unsignedBigInteger('service_cost_id');
            $table->unsignedBigInteger('amount');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['requested_service_id', 'sub_service_cost_id'], 'uniqRqCost');
            $table->foreign('requested_service_id', 'requested_service_cost_requested_service_id_foreign')
                  ->references('id')
                  ->on('requested_services')
                  ->onDelete('cascade');
            $table->foreign('sub_service_cost_id', 'requested_service_cost_sub_service_cost_id_foreign')
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
        Schema::dropIfExists('requested_service_cost');
    }
};

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
        Schema::create('cost_order', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('service_cost_id')->nullable();
            $table->unsignedBigInteger('service_cost_item')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('sub_service_cost_id');
            $table->foreign('service_cost_id', 'cost_order_service_cost_id_foreign')
                  ->references('id')
                  ->on('service_cost')
                  ->onDelete('cascade');
            $table->foreign('service_cost_item', 'cost_order_service_cost_item_foreign')
                  ->references('id')
                  ->on('service_cost')
                  ->onDelete('cascade');
            $table->foreign('service_id', 'cost_order_service_id_foreign')
                  ->references('id')
                  ->on('services')
                  ->onDelete('cascade');
            $table->foreign('sub_service_cost_id', 'cost_order_sub_service_cost_id_foreign')
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
        Schema::dropIfExists('cost_order');
    }
};

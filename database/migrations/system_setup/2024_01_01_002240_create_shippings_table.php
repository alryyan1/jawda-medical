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
        Schema::create('shippings', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('shipping_item_id');
            $table->string('name', 255);
            $table->string('phone', 255);
            $table->string('express', 255);
            $table->string('ctn', 255)->nullable();
            $table->string('cbm', 255)->nullable();
            $table->string('kg', 255)->nullable();
            $table->string('prefix', 255);
            $table->unsignedBigInteger('shipping_state_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('shipping_item_id', 'shippings_shipping_item_id_foreign')
                  ->references('id')
                  ->on('shipping_items')
                  ->onDelete('cascade');
            $table->foreign('shipping_state_id', 'shippings_shipping_state_id_foreign')
                  ->references('id')
                  ->on('shipping_states')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shippings');
    }
};

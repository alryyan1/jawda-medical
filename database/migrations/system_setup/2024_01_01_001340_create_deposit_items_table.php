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
        Schema::create('deposit_items', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('deposit_id');
            $table->integer('quantity')->default(0);
            $table->double('cost')->default(0);
            $table->string('batch', 255)->nullable();
            $table->date('expire')->nullable();
            $table->string('notes', 255)->nullable();
            $table->string('barcode', 255)->nullable();
            $table->boolean('return')->default(0);
            $table->unsignedBigInteger('user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('sell_price');
            $table->string('vat_cost');
            $table->string('vat_sell');
            $table->integer('free_quantity');
            $table->unique(['item_id', 'deposit_id'], 'deposit_items_item_id_deposit_id_unique');
            $table->foreign('deposit_id', 'deposit_items_deposit_id_foreign')
                  ->references('id')
                  ->on('deposits')
                  ->onDelete('cascade');
            $table->foreign('item_id', 'deposit_items_item_id_foreign')
                  ->references('id')
                  ->on('items')
                  ->onDelete('cascade');
            $table->foreign('user_id', 'deposit_items_user_id_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_items');
    }
};

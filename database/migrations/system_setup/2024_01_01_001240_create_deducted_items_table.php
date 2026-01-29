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
        Schema::create('deducted_items', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('shift_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('deduct_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->integer('strips');
            $table->string('box');
            $table->integer('discount');
            $table->string('price');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('client_id', 'deducted_items_client_id_foreign')
                  ->references('id')
                  ->on('clients')
                  ->onDelete('cascade');
            $table->foreign('deduct_id', 'deducted_items_deduct_id_foreign')
                  ->references('id')
                  ->on('deducts')
                  ->onDelete('cascade');
            $table->foreign('item_id', 'deducted_items_item_id_foreign')
                  ->references('id')
                  ->on('items')
                  ->onDelete('cascade');
            $table->foreign('shift_id', 'deducted_items_shift_id_foreign')
                  ->references('id')
                  ->on('shifts')
                  ->onDelete('cascade');
            $table->foreign('user_id', 'deducted_items_user_id_foreign')
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
        Schema::dropIfExists('deducted_items');
    }
};

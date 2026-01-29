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
        Schema::create('debits', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('deduct_id')->nullable();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('payment_type_id');
            $table->double('paid_amount');
            $table->longText('notes')->nullable();
            $table->date('paid_date');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('client_id', 'debits_client_id_foreign')
                  ->references('id')
                  ->on('clients')
                  ->onDelete('cascade');
            $table->foreign('deduct_id', 'debits_deduct_id_foreign')
                  ->references('id')
                  ->on('deducts')
                  ->onDelete('cascade');
            $table->foreign('payment_type_id', 'debits_payment_type_id_foreign')
                  ->references('id')
                  ->on('payment_types')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debits');
    }
};

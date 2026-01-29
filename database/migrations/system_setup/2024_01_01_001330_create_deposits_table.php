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
        Schema::create('deposits', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('supplier_id');
            $table->string('bill_number', 255);
            $table->date('bill_date');
            $table->boolean('complete')->default(0);
            $table->boolean('paid')->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('payment_method', 255);
            $table->string('discount');
            $table->double('vat_sell')->default(0);
            $table->double('vat_cost')->default(0);
            $table->boolean('is_locked');
            $table->boolean('showAll')->default(1);
            $table->foreign('supplier_id', 'deposits_supplier_id_foreign')
                  ->references('id')
                  ->on('suppliers')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};

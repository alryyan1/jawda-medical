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
        Schema::create('payment_suppliers', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('supplier_id');
            $table->string('amount');
            $table->unsignedBigInteger('user_id');
            $table->enum('type_of_payment', ["cash","bank","visa","mobile_transfer"])->default('cash');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('supplier_id', 'payment_suppliers_supplier_id_foreign')
                  ->references('id')
                  ->on('suppliers')
                  ->onDelete('cascade');
            $table->foreign('user_id', 'payment_suppliers_user_id_foreign')
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
        Schema::dropIfExists('payment_suppliers');
    }
};

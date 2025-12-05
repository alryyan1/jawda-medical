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
            $table->bigIncrements('id');
            $table->foreignId('deduct_id')->nullable()->constrained('deducts')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('payment_type_id')->constrained('payment_types')->onDelete('cascade');
            $table->double('paid_amount');
            $table->longText('notes')->nullable();
            $table->date('paid_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debets');
    }
};

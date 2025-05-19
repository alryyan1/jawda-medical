<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_entries', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('finance_account_id');
            $table->foreign('finance_account_id')->references('id')->on('finance_accounts')->onDelete('cascade'); // Or restrict

            $table->unsignedBigInteger('finance_entry_id');
            $table->foreign('finance_entry_id')->references('id')->on('finance_entries')->onDelete('cascade');

            $table->decimal('amount', 10, 3); // Amount with 3 decimal places

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_entries');
    }
};
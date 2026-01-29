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
        Schema::create('credit_entries', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('finance_account_id');
            $table->unsignedBigInteger('finance_entry_id');
            $table->double('amount');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('finance_account_id', 'credit_entries_finance_account_id_foreign')
                  ->references('id')
                  ->on('finance_accounts')
                  ->onDelete('cascade');
            $table->foreign('finance_entry_id', 'credit_entries_finance_entry_id_foreign')
                  ->references('id')
                  ->on('finance_entries')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_entries');
    }
};

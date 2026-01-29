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
        Schema::create('petty_cash_permissions', function (Blueprint $table) {
            $table->id('id');
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->string('beneficiary', 255);
            $table->text('description')->nullable();
            $table->string('pdf_file', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->bigInteger('finance_entry_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('signature_file_name', 255);
            $table->string('phone', 255);
            $table->unsignedBigInteger('user_approved')->nullable();
            $table->unsignedBigInteger('financial_auditor')->nullable();
            $table->dateTime('user_approved_time')->nullable();
            $table->dateTime('auditor_approved_time')->nullable();
            $table->foreign('financial_auditor', 'petty_cash_permissions_financial_auditor_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('user_approved', 'petty_cash_permissions_user_approved_foreign')
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
        Schema::dropIfExists('petty_cash_permissions');
    }
};

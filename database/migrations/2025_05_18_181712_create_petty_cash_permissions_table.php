<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_permissions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->string('beneficiary');
            $table->text('description')->nullable();
            $table->string('pdf_file')->nullable(); // Path to PDF

            $table->unsignedBigInteger('finance_entry_id')->nullable(); // Corrected to unsigned for FK
            $table->foreign('finance_entry_id')->references('id')->on('finance_entries')->onDelete('set null');

            $table->unsignedBigInteger('user_id'); // User requesting
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');

            $table->string('signature_file_name'); // Path to signature
            $table->string('phone');

            $table->unsignedBigInteger('user_approved')->nullable(); // User who approved
            $table->foreign('user_approved')->references('id')->on('users')->onDelete('set null');

            $table->unsignedBigInteger('financial_auditor')->nullable(); // User who audited
            $table->foreign('financial_auditor')->references('id')->on('users')->onDelete('set null');

            $table->dateTime('user_approved_time')->nullable();
            $table->dateTime('auditor_approved_time')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_permissions');
    }
};
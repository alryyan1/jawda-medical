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
        Schema::create('companies', function (Blueprint $table) {
            $table->id('id');
            $table->string('name', 255);
            $table->string('lab_endurance');
            $table->string('service_endurance');
            $table->boolean('status');
            $table->integer('lab_roof');
            $table->integer('service_roof');
            $table->string('phone', 255);
            $table->string('email', 255);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('finance_account_id')->nullable();
            $table->string('lab2lab_firestore_id', 255)->nullable();
            $table->foreign('finance_account_id', 'companies_finance_account_id_foreign')
                  ->references('id')
                  ->on('finance_accounts')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

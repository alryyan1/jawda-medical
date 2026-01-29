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
        Schema::create('doctors', function (Blueprint $table) {
            $table->id('id');
            $table->string('name', 255);
            $table->string('firebase_id', 255)->nullable();
            $table->string('phone', 255);
            $table->double('cash_percentage');
            $table->double('company_percentage');
            $table->double('static_wage');
            $table->double('lab_percentage');
            $table->boolean('is_default')->default(0);
            $table->unsignedBigInteger('specialist_id');
            $table->unsignedBigInteger('sub_specialist_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('start');
            $table->string('image', 255)->nullable();
            $table->unsignedBigInteger('finance_account_id')->nullable();
            $table->unsignedBigInteger('finanace_account_id_insurance')->nullable();
            $table->boolean('calc_insurance')->default(0);
            $table->foreign('finanace_account_id_insurance', 'doctors_finanace_account_id_insurance_foreign')
                  ->references('id')
                  ->on('finance_accounts')
                  ->onDelete('cascade');
            $table->foreign('finance_account_id', 'doctors_finance_account_id_foreign')
                  ->references('id')
                  ->on('finance_accounts')
                  ->onDelete('cascade');
            $table->foreign('specialist_id', 'doctors_specialist_id_foreign')
                  ->references('id')
                  ->on('specialists')
                  ->onDelete('cascade');
            $table->foreign('sub_specialist_id', 'doctors_sub_specialist_id_foreign')
                  ->references('id')
                  ->on('sub_specialists')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};

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
        Schema::create('admission_transactions', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('admission_id');
            $table->enum('type', ["debit","credit"]);
            $table->decimal('amount', 10, 2);
            $table->string('description', 255);
            $table->string('reference_type', 255)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('is_bank')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('admission_id', 'admission_transactions_admission_id_foreign')
                  ->references('id')
                  ->on('admissions')
                  ->onDelete('cascade');
            $table->foreign('user_id', 'admission_transactions_user_id_foreign')
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
        Schema::dropIfExists('admission_transactions');
    }
};

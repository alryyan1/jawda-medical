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
        Schema::create('requested_surgery_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requested_surgery_id');
            $table->enum('type', ['debit', 'credit']);
            $table->decimal('amount', 12, 2);
            $table->string('description', 255);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('requested_surgery_id', 'rst_rs_id_foreign')
                ->references('id')
                ->on('requested_surgeries')
                ->onDelete('cascade');

            $table->foreign('user_id', 'rst_user_id_foreign')
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
        Schema::dropIfExists('requested_surgery_transactions');
    }
};

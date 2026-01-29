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
        Schema::create('finance_entries', function (Blueprint $table) {
            $table->id('id');
            $table->text('description');
            $table->boolean('transfer');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('doctor_shift_id')->nullable();
            $table->unsignedBigInteger('user_created')->nullable();
            $table->boolean('is_net');
            $table->unsignedBigInteger('user_net')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->string('file_name', 255);
            $table->boolean('cancel');
            $table->foreign('doctor_shift_id', 'finance_entries_doctor_shift_id_foreign')
                  ->references('id')
                  ->on('doctor_shifts')
                  ->onDelete('cascade');
            $table->foreign('user_created', 'finance_entries_user_created_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('user_net', 'finance_entries_user_net_foreign')
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
        Schema::dropIfExists('finance_entries');
    }
};

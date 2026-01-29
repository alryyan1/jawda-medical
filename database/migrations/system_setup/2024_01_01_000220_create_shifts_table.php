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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id('id');
            $table->string('total');
            $table->string('bank');
            $table->string('expenses');
            $table->boolean('touched');
            $table->dateTime('closed_at')->nullable();
            $table->boolean('is_closed')->default(0);
            $table->unsignedBigInteger('user_id')->default(1);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('pharmacy_entry')->nullable();
            $table->unsignedBigInteger('user_closed')->nullable();
            $table->foreign('user_closed', 'shifts_user_closed_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('user_id', 'shifts_user_id_foreign')
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
        Schema::dropIfExists('shifts');
    }
};

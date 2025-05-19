<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_entries', function (Blueprint $table) {
            $table->id();
            $table->text('description');
            $table->boolean('transfer'); // Indicates if it's a transfer transaction
            
            $table->unsignedBigInteger('doctor_shift_id')->nullable();
            $table->foreign('doctor_shift_id')->references('id')->on('doctor_shifts')->onDelete('set null');

            $table->unsignedBigInteger('user_created')->nullable(); // User who created the entry
            $table->foreign('user_created')->references('id')->on('users')->onDelete('set null');

            $table->boolean('is_net'); // Related to netting process
            $table->unsignedBigInteger('user_net')->nullable(); // User who performed netting
            $table->foreign('user_net')->references('id')->on('users')->onDelete('set null');

            $table->unsignedBigInteger('shift_id')->nullable();
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null');

            $table->string('file_name')->nullable(); // Defaulted to nullable, schema has NOT NULL
            $table->boolean('cancel')->default(false); // Defaulted to false, schema has NOT NULL

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_entries');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requested_service_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_service_id')
                  ->constrained('requested_services')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->constrained('users');
            $table->text('diagnosis')->nullable();
            $table->boolean('complete')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_printed')->default(false);
            $table->foreignId('printed_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requested_service_diagnoses');
    }
};

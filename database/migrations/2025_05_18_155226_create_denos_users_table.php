<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('denos_users', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('shift_id');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('cascade');

            $table->unsignedBigInteger('deno_id'); // Foreign key to cash_tally table
            $table->foreign('deno_id')->references('id')->on('cash_tally')->onDelete('cascade');

            $table->integer('amount'); // The count of this denomination

            // No timestamps in original schema
            // $table->unique(['user_id', 'shift_id', 'deno_id']); // Optional: if each user can only have one entry per denomination per shift
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('denos_users');
    }
};
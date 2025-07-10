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
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('shift_id')->constrained()->onDelete('cascade');
            $table->foreignId('deno_id')->constrained('denos')->onDelete('cascade');
            $table->integer('count'); // Changed 'amount' to 'count' for clarity (e.g., 5 bills of 1000)
            $table->timestamps(); // Useful for tracking when the count was recorded

            $table->unique(['user_id', 'shift_id', 'deno_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('denos_users');
    }
};
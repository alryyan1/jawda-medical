<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Drop existing table to ensure idempotent re-runs
        Schema::dropIfExists('denos_users');

        Schema::create('denos_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('deno_id')->constrained('denos')->cascadeOnDelete();
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'shift_id', 'deno_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('denos_users');
    }
};

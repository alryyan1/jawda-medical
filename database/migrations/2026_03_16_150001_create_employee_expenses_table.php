<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->foreignId('shift_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Recorded by
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_expenses');
    }
};

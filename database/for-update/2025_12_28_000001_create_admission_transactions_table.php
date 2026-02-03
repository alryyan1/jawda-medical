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
            $table->id();
            $table->foreignId('admission_id')->constrained('admissions')->onDelete('cascade');
            $table->enum('type', ['debit', 'credit']);
            $table->decimal('amount', 10, 2);
            $table->string('description');
            $table->string('reference_type')->nullable(); // 'service', 'deposit', 'manual'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('is_bank')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            
            // Indexes
            $table->index(['admission_id', 'type']);
            $table->index(['reference_type', 'reference_id']);
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



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
        Schema::create('doctor_shifts', function (Blueprint $table) {
           $table->id();
            $table->foreignId('user_id')->constrained('users')->comment('User who initiated this doctor shift (e.g., doctor themselves or admin)');
            $table->foreignId('shift_id')->constrained('shifts')->comment('The general clinic shift this belongs to');
            $table->foreignId('doctor_id')->constrained('doctors');
            
            // Status: 1 = active/open, 0 = closed. Or use an ENUM.
            $table->boolean('status')->default(true)->comment('Is this doctor shift session currently active?'); 
            
            $table->timestamp('start_time')->nullable()->comment('Actual start time of the doctor working');
            $table->timestamp('end_time')->nullable()->comment('Actual end time of the doctor working');
            
            // These boolean flags were in your original doctor_shifts schema, purpose might need clarification
            // If they are about proving financial reconciliation for this doctor's shift earnings:
            $table->boolean('is_cash_revenue_prooved')->default(false);
            $table->boolean('is_cash_reclaim_prooved')->default(false);
            $table->boolean('is_company_revenue_prooved')->default(false);
            $table->boolean('is_company_reclaim_prooved')->default(false);

            $table->timestamps(); // created_at, updated_at
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_shifts');
    }
};
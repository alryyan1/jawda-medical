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
        Schema::create('requested_surgery_finances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_surgery_id')->constrained('requested_surgeries')->cascadeOnDelete();
            $table->foreignId('admission_id')->constrained('admissions');
            $table->foreignId('surgery_id')->constrained('surgical_operations');
            $table->foreignId('finance_charge_id')->constrained('surgical_operation_charges');
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requested_surgery_finances');
    }
};

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
        Schema::create('surgical_operation_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surgical_operation_id')->constrained('surgical_operations')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('amount', 10, 2)->default(0);
            $table->enum('reference_type', ['total', 'charge'])->nullable();
            $table->foreignId('reference_charge_id')->nullable()->constrained('surgical_operation_charges')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surgical_operation_charges');
    }
};

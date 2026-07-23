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
        Schema::create('visit_prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_visit_id')
                ->constrained('doctorvisits')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users');
            $table->text('notes')->nullable();
            $table->boolean('is_printed')->default(false);
            $table->foreignId('printed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_prescriptions');
    }
};

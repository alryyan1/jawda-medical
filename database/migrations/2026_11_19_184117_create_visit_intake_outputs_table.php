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
        Schema::create('visit_intake_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_visit_id')
                ->constrained('doctorvisits')
                ->cascadeOnDelete();
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('recorded_at')->useCurrent();
            $table->decimal('feeding', 8, 2)->default(0);
            $table->decimal('iv_fluid', 8, 2)->default(0);
            $table->decimal('maintenance', 8, 2)->default(0);
            $table->decimal('urine_output', 8, 2)->default(0);
            $table->decimal('insensible_loss', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_intake_outputs');
    }
};

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
        Schema::create('hl7_messages', function (Blueprint $table) {
            $table->id();
            $table->text('raw_message');
            $table->string('device', 50)->nullable();
            $table->string('message_type', 10)->nullable();
            $table->string('patient_id', 50)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['device', 'message_type']);
            $table->index('patient_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hl7_messages');
    }
};

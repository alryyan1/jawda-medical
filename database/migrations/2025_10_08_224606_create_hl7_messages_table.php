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
            $table->text('raw_message')->comment('Raw HL7 message received from device');
            $table->string('device_type')->nullable()->comment('Type of device that sent the message');
            $table->string('message_type')->nullable()->comment('HL7 message type (e.g., ORU^R01)');
            $table->string('sending_facility')->nullable()->comment('Sending facility from MSH.4');
            $table->string('sending_application')->nullable()->comment('Sending application from MSH.3');
            $table->string('receiving_facility')->nullable()->comment('Receiving facility from MSH.6');
            $table->string('receiving_application')->nullable()->comment('Receiving application from MSH.5');
            $table->string('message_control_id')->nullable()->comment('Message control ID from MSH.10');
            $table->timestamp('message_datetime')->nullable()->comment('Message datetime from MSH.7');
            $table->json('parsed_data')->nullable()->comment('Parsed HL7 message data');
            $table->boolean('processed')->default(false)->comment('Whether the message has been processed');
            $table->text('processing_notes')->nullable()->comment('Notes about message processing');
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['device_type', 'created_at']);
            $table->index(['message_type', 'created_at']);
            $table->index(['processed', 'created_at']);
            $table->index('message_control_id');
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

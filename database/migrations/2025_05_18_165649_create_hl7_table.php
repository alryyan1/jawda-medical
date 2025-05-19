<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hl7', function (Blueprint $table) {
            $table->id();
            $table->longText('msg'); // The raw HL7 message content
            // No timestamps in original schema, but might be useful for tracking when messages were received/processed.
            // $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hl7');
    }
};
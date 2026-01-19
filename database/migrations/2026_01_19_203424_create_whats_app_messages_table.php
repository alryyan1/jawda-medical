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
        Schema::create('whats_app_messages', function (Blueprint $table) {
            $table->id();
            $table->string('waba_id')->nullable();
            $table->string('phone_number_id')->nullable();
            $table->string('to')->nullable();
            $table->string('from')->nullable();
            $table->string('type')->default('text'); // text, image, document, etc.
            $table->text('body')->nullable();
            $table->string('status')->default('sent'); // sent, delivered, read, failed, received
            $table->string('message_id')->nullable()->index();
            $table->string('direction')->default('outgoing'); // outgoing, incoming
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whats_app_messages');
    }
};

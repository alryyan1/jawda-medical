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
            $table->id('id');
            $table->string('waba_id', 255)->nullable();
            $table->string('phone_number_id', 255)->nullable();
            $table->string('to', 255)->nullable();
            $table->string('from', 255)->nullable();
            $table->string('type', 255)->default('text');
            $table->text('body')->nullable();
            $table->string('status', 255)->default('sent');
            $table->string('message_id', 255)->nullable();
            $table->string('direction', 255)->default('outgoing');
            $table->longText('raw_payload')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
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

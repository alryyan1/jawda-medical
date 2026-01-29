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
        Schema::create('requested_service_deposits', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('requested_service_id');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('is_bank');
            $table->boolean('is_claimed');
            $table->unsignedBigInteger('shift_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requested_service_deposits');
    }
};

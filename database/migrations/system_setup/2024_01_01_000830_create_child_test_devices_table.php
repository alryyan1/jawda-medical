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
        Schema::create('child_test_devices', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('child_test_id');
            $table->unsignedBigInteger('device_id');
            $table->string('normal_range', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('child_test_devices');
    }
};

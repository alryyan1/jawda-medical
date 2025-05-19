<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_test_devices', function (Blueprint $table) {
            $table->id(); // This pivot has an extra attribute 'normal_range', so an ID is fine.

            $table->unsignedBigInteger('child_test_id');
            $table->foreign('child_test_id')->references('id')->on('child_tests')->onDelete('cascade');

            $table->unsignedBigInteger('device_id');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');

            $table->string('normal_range'); // Device-specific normal range text

            // $table->unique(['child_test_id', 'device_id']); // Optional: if a child test can only have one normal range per device
            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_test_devices');
    }
};
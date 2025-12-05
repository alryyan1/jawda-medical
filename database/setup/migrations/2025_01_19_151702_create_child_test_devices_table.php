<?php

use App\Models\ChildTest;
use App\Models\Device;
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
            $table->id();
            $table->foreignIdFor(ChildTest::class);
            $table->foreignIdFor(Device::class);
            $table->string('normal_range');
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

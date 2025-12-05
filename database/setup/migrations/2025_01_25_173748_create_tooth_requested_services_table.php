<?php

use App\Models\RequestedService;
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
        Schema::create('tooth_requested_services', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(RequestedService::class);
            $table->unsignedBigInteger('tooth_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tooth_requested_services');
    }
};

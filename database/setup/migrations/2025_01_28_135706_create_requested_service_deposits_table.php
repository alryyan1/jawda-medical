<?php

use App\Models\RequestedService;
use App\Models\User;
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
            $table->id();
            $table->foreignIdFor(RequestedService::class);
            $table->unsignedBigInteger('amount');
            $table->foreignIdFor(User::class);
            $table->timestamps();
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

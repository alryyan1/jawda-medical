<?php

use App\Models\Doctor;
use App\Models\SubServiceCost;
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
        Schema::create('doctor_service_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Doctor::class)->constrained();
            $table->foreignIdFor(SubServiceCost::class)->constrained();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_service_costs');
    }
};

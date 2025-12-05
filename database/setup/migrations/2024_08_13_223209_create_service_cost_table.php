<?php

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
        Schema::create('service_cost', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SubServiceCost::class)->constrained();
            $table->foreignIdFor(\App\Models\Service::class)->constrained();
            $table->float('percentage')->default(0);
            $table->float('fixed',11,2);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_cost');
    }
};

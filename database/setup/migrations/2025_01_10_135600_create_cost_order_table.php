<?php

use App\Models\RequestedService;
use App\Models\Service;
use App\Models\ServiceCost;
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
        Schema::create('cost_order', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Service::class)->constrained();
            $table->foreignIdFor(ServiceCost::class,'service_cost_id')->nullable()->constrained()->references('id')->on('service_cost')->cascadeOnUpdate()->cascadeOnUpdate();
            $table->foreignIdFor(ServiceCost::class,'service_cost_item')->nullable()->constrained()->references('id')->on('service_cost')->cascadeOnUpdate()->cascadeOnUpdate();
            // $table->unique(['service_cost_id','service_id','service_cost_item'],'unisqCost');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_order');
    }
};

<?php

use App\Models\RequestedService;
use App\Models\ServiceCost;
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
        Schema::create('requested_service_cost', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(RequestedService::class)->constrained();
            $table->foreignIdFor(SubServiceCost::class)->constrained();
            $table->foreignIdFor(ServiceCost::class);
            $table->unsignedBigInteger('amount');
            $table->timestamps();
            //unique
            $table->unique(['requested_service_id','sub_service_cost_id'],'uniqRqCost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requested_service_cost');
    }
};

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
        Schema::table('cost_order', function (Blueprint $table) {
            // $table->dropForeign('cost_order_service_cost_item_foreign');
            $table->foreignIdFor(SubServiceCost::class)->constrained();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cost_order', function (Blueprint $table) {
            $table->dropForeignIdFor(SubServiceCost::class);
            // $table->string('service_cost_item')->nullable();
        });
    }
};

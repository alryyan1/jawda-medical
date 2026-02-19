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
        Schema::dropIfExists('operation_finance_items');
        Schema::dropIfExists('operation_costs');
        Schema::dropIfExists('operation_items');
        Schema::dropIfExists('operations');
        Schema::dropIfExists('medical_operations'); // Just in case, though likely same table or not existing
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

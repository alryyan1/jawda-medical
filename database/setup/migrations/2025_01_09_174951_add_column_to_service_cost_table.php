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
        Schema::table('service_cost', function (Blueprint $table) {
            $arr = ['total', 'after cost'];
            // make enum for above array
            $table->enum('cost_type', ['total','after cost'])->default('total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_cost', function (Blueprint $table) {
            $table->dropColumn('cost_type');
        });
    }
};

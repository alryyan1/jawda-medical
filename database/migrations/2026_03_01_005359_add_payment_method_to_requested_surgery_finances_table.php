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
        Schema::table('requested_surgery_finances', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'bankak'])->default('cash')->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requested_surgery_finances', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};

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
        Schema::table('operation_costs', function (Blueprint $table) {
            $table->boolean('is_surgeon')->default(false)->after('fixed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operation_costs', function (Blueprint $table) {
            $table->dropColumn('is_surgeon');
        });
    }
};

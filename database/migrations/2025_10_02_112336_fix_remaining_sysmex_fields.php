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
        Schema::table('sysmex', function (Blueprint $table) {
            // Make remaining required fields nullable
            $table->decimal('hct', 5, 2)->nullable()->change();
            $table->decimal('wbc', 8, 3)->nullable()->change();
            $table->decimal('rbc', 8, 3)->nullable()->change();
            $table->decimal('hgb', 5, 2)->nullable()->change();
            $table->decimal('plt', 8, 3)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sysmex', function (Blueprint $table) {
            // Revert fields to not nullable (this might fail if there are null values)
            $table->decimal('hct', 5, 2)->nullable(false)->change();
            $table->decimal('wbc', 8, 3)->nullable(false)->change();
            $table->decimal('rbc', 8, 3)->nullable(false)->change();
            $table->decimal('hgb', 5, 2)->nullable(false)->change();
            $table->decimal('plt', 8, 3)->nullable(false)->change();
        });
    }
};

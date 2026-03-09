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
        Schema::table('doctor_services', function (Blueprint $table) {
            $table->decimal('percentage', 8, 2)->nullable()->change();
            $table->decimal('fixed', 12, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_services', function (Blueprint $table) {
            $table->string('percentage')->nullable(false)->change();
            $table->string('fixed')->nullable(false)->change();
        });
    }
};

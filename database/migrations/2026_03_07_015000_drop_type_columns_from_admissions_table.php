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
        Schema::table('admissions', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['booking_type', 'admission_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $blueprint) {
            $blueprint->string('booking_type')->nullable();
            $blueprint->string('admission_type')->nullable();
        });
    }
};

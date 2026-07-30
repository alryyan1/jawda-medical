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
        Schema::table('visit_vitals', function (Blueprint $table) {
            $table->unsignedTinyInteger('pain_scale')->nullable()->after('respiratory_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visit_vitals', function (Blueprint $table) {
            $table->dropColumn('pain_scale');
        });
    }
};

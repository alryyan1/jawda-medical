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
        Schema::table('deducts', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\Doctorvisit::class)->nullable();
            $table->double('endurance_percentage')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deducts', function (Blueprint $table) {
            $table->dropForeign(\App\Models\Doctorvisit::class);
            $table->dropColumn('endurance_percentage');
        });
    }
};

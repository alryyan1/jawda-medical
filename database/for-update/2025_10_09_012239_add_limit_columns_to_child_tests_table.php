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
        Schema::table('child_tests', function (Blueprint $table) {
            $table->float('lower_limit')->nullable()->after('upper');
            $table->float('mean')->nullable()->after('lower_limit');
            $table->float('upper_limit')->nullable()->after('mean');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('child_tests', function (Blueprint $table) {
            $table->dropColumn(['lower_limit', 'mean', 'upper_limit']);
        });
    }
};

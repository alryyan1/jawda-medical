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
        Schema::table('main_tests', function (Blueprint $table) {
            $table->boolean('hide_unit')->default(false)->after('timer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('main_tests', function (Blueprint $table) {
            $table->dropColumn('hide_unit');
        });
    }
};

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
            //enum
            $table->enum('payment_method',['cash','bankak']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deducts', function (Blueprint $table) {
             $table->dropColumn('payment_method');
        });
    }
};

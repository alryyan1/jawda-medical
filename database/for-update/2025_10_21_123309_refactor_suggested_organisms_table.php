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
        Schema::table('suggested_organisms', function (Blueprint $table) {
            // Drop the old columns
            $table->dropColumn(['sensitive_antibiotics', 'resistant_antibiotics']);
            
            // Add the new antibiotics column
            $table->text('antibiotics')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suggested_organisms', function (Blueprint $table) {
            // Drop the new column
            $table->dropColumn('antibiotics');
            
            // Restore the old columns
            $table->string('sensitive_antibiotics')->nullable();
            $table->string('resistant_antibiotics')->nullable();
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, delete all existing records to avoid conflicts
        DB::table('suggested_organisms')->delete();
        
        Schema::table('suggested_organisms', function (Blueprint $table) {
            // Drop all columns except id
            $table->dropColumn(['organism_name', 'antibiotics', 'usage_count', 'created_at', 'updated_at']);
            
            // Add the new name column
            $table->string('name')->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suggested_organisms', function (Blueprint $table) {
            // Drop the new column
            $table->dropColumn('name');
            
            // Restore the old columns
            $table->string('organism_name')->unique();
            $table->text('antibiotics')->nullable();
            $table->integer('usage_count')->default(0);
            $table->timestamps();
        });
    }
};
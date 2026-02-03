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
        Schema::table('admissions', function (Blueprint $table) {
            // Drop foreign key constraint first if it exists
            $table->dropForeign('admissions_bed_id_foreign');
            
            // Make bed_id nullable
            $table->unsignedBigInteger('bed_id')->nullable()->change();
            
            // Add booking_type column
            $table->enum('booking_type', ['bed', 'room'])->default('bed')->after('bed_id');
            
            // Re-add foreign key constraint with the same name
            $table->foreign('bed_id', 'admissions_bed_id_foreign')->references('id')->on('beds')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign('admissions_bed_id_foreign');
            
            // Remove booking_type column
            $table->dropColumn('booking_type');
            
            // Make bed_id required again
            $table->unsignedBigInteger('bed_id')->nullable(false)->change();
            
            // Re-add foreign key constraint with the same name
            $table->foreign('bed_id', 'admissions_bed_id_foreign')->references('id')->on('beds')->onDelete('cascade');
        });
    }
};

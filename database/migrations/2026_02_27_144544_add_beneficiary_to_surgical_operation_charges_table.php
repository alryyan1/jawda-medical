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
        Schema::table('surgical_operation_charges', function (Blueprint $table) {
            $table->enum('beneficiary', ['center', 'staff'])->default('center');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surgical_operation_charges', function (Blueprint $table) {
            $table->dropColumn('beneficiary');
        });
    }
};

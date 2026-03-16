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
        Schema::table('returned_lab_requests', function (Blueprint $table) {
            $table->string('return_reason')->nullable()->after('returned_payment_method');
        });

        Schema::table('returned_requested_services', function (Blueprint $table) {
            $table->string('return_reason')->nullable()->after('returned_payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('returned_lab_requests', function (Blueprint $table) {
            $table->dropColumn('return_reason');
        });

        Schema::table('returned_requested_services', function (Blueprint $table) {
            $table->dropColumn('return_reason');
        });
    }
};

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
        Schema::table('employee_expenses', function (Blueprint $blueprint) {
            $blueprint->decimal('cash_amount', 15, 2)->default(0)->after('amount');
            $blueprint->decimal('bank_amount', 15, 2)->default(0)->after('cash_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_expenses', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['cash_amount', 'bank_amount']);
        });
    }
};

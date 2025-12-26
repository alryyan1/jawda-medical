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
        Schema::table('admission_requested_services', function (Blueprint $table) {
            $table->dropColumn(['amount_paid', 'is_paid', 'bank', 'payment_source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_requested_services', function (Blueprint $table) {
            $table->decimal('amount_paid', 10, 2)->default(0)->after('endurance');
            $table->boolean('is_paid')->default(false)->after('amount_paid');
            $table->boolean('bank')->default(false)->after('discount_per');
            $table->enum('payment_source', ['deposit', 'cash'])->default('cash')->after('bank');
        });
    }
};


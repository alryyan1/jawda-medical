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
        Schema::table('petty_cash_permissions', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\User::class,'financial_auditor')->nullable()->constrained()->references('id')->on('users');
            $table->dateTime('user_approved_time')->nullable();
            $table->dateTime('auditor_approved_time')->nullable();
            // $table->string('auditor_logo')->nullable();
            // $table->string('auditor_approved_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('petty_cash_petty_cash_permissions', function (Blueprint $table) {
            //
        });
    }
};

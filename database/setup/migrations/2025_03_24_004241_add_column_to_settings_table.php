<?php

use App\Models\FinanceAccount;
use App\Models\FinanceEntry;
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
        Schema::table('settings', function (Blueprint $table) {
            $table->foreignIdFor(FinanceAccount::class,'company_account_id')->nullable()->constrained()->references('id')->on('finance_accounts');;
            $table->foreignIdFor(FinanceAccount::class,'endurance_account_id')->nullable()->constrained()->references('id')->on('finance_accounts');;
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            //
        });
    }
};

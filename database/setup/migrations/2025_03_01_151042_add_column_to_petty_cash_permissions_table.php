<?php

use App\Models\FinanceEntry;
use App\Models\User;
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
            $table->foreignIdFor(FinanceEntry::class);
            $table->foreignIdFor(User::class);
            $table->dropColumn(['finance_account_id','permission_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('petty_cash_permissions', function (Blueprint $table) {
            
        });
    }
};

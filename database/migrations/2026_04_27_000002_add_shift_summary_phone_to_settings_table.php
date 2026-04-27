<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'shift_summary_phone')) {
                $table->string('shift_summary_phone')->nullable()->after('payment_cancellation_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'shift_summary_phone')) {
                $table->dropColumn('shift_summary_phone');
            }
        });
    }
};

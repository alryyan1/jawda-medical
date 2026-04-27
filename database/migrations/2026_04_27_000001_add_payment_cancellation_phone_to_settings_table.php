<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'payment_cancellation_phone')) {
                $table->string('payment_cancellation_phone')->nullable()->after('whatsapp_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'payment_cancellation_phone')) {
                $table->dropColumn('payment_cancellation_phone');
            }
        });
    }
};

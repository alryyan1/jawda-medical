<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'lab_welcome_sms_message')) {
                $table->text('lab_welcome_sms_message')->nullable()->after('shift_summary_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'lab_welcome_sms_message')) {
                $table->dropColumn('lab_welcome_sms_message');
            }
        });
    }
};

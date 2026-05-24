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
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'airtel_sms_api_key',
                'airtel_sms_base_url',
                'airtel_sms_sender',
                'settings_enable_Sms_front'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('airtel_sms_api_key')->nullable();
            $table->string('airtel_sms_base_url')->nullable();
            $table->string('airtel_sms_sender')->nullable();
            $table->boolean('settings_enable_Sms_front')->default(false);
        });
    }
};

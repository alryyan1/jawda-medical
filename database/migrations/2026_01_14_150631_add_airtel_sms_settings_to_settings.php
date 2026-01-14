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
            $table->string('airtel_sms_api_key')->nullable()->after('whatsapp_number');
            $table->string('airtel_sms_base_url')->default('https://www.airtel.sd')->after('airtel_sms_api_key');
            $table->string('airtel_sms_sender')->default('JAWDA')->after('airtel_sms_base_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['airtel_sms_api_key', 'airtel_sms_base_url', 'airtel_sms_sender']);
        });
    }
};

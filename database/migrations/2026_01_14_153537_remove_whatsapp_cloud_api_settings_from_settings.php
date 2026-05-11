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
            $cols = [
                'cloud_api_token',
                'phone_number_id',
                'whatsapp_cloud_waba_id',
                'whatsapp_cloud_api_version'
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('cloud_api_token')->nullable();
            $table->string('phone_number_id')->nullable();
            $table->string('whatsapp_cloud_waba_id')->nullable();
            $table->string('whatsapp_cloud_api_version')->nullable();
        });
    }
};

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
            // Add Ultramsg WhatsApp API fields
            $table->string('ultramsg_instance_id')->nullable()->after('token');
            $table->string('ultramsg_token')->nullable()->after('ultramsg_instance_id');
            $table->string('ultramsg_base_url')->default('https://api.ultramsg.com')->after('ultramsg_token');
            $table->string('ultramsg_default_country_code')->default('249')->after('ultramsg_base_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Remove Ultramsg WhatsApp API fields
            $table->dropColumn([
                'ultramsg_instance_id',
                'ultramsg_token',
                'ultramsg_base_url',
                'ultramsg_default_country_code'
            ]);
        });
    }
};

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
            // Make boolean columns nullable (only those that are currently NOT NULL)
            $table->boolean('is_header')->nullable()->change();
            $table->boolean('is_footer')->nullable()->change();
            $table->boolean('is_logo')->nullable()->change();
            $table->boolean('disable_doctor_service_check')->nullable()->change();
            $table->boolean('gov')->nullable()->change();
            $table->boolean('country')->nullable()->change();
            $table->boolean('barcode')->nullable()->change();
            $table->boolean('show_water_mark')->nullable()->change();
            $table->boolean('send_result_after_auth')->nullable()->change();
            $table->boolean('send_result_after_result')->nullable()->change();
            $table->boolean('edit_result_after_auth')->nullable()->change();
            $table->boolean('send_welcome_message')->nullable()->change();

            // Make string columns nullable (only those that are currently NOT NULL)
            $table->string('currency')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->string('vatin')->nullable()->change();
            $table->string('cr')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('address')->nullable()->change();
            $table->string('ultramsg_base_url')->nullable()->change();
            $table->string('ultramsg_default_country_code')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Revert boolean columns to not nullable
            $table->boolean('is_header')->nullable(false)->change();
            $table->boolean('is_footer')->nullable(false)->change();
            $table->boolean('is_logo')->nullable(false)->change();
            $table->boolean('disable_doctor_service_check')->nullable(false)->change();
            $table->boolean('gov')->nullable(false)->change();
            $table->boolean('country')->nullable(false)->change();
            $table->boolean('barcode')->nullable(false)->change();
            $table->boolean('show_water_mark')->nullable(false)->change();
            $table->boolean('send_result_after_auth')->nullable(false)->change();
            $table->boolean('send_result_after_result')->nullable(false)->change();
            $table->boolean('edit_result_after_auth')->nullable(false)->change();
            $table->boolean('send_welcome_message')->nullable(false)->change();

            // Revert string columns to not nullable
            $table->string('currency')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
            $table->string('vatin')->nullable(false)->change();
            $table->string('cr')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->string('address')->nullable(false)->change();
            $table->string('ultramsg_base_url')->nullable(false)->change();
            $table->string('ultramsg_default_country_code')->nullable(false)->change();
        });
    }
};

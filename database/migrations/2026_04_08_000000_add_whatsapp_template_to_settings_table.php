<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'whatsapp_number')) {
                $table->string('whatsapp_number')->nullable();
            }
            if (! Schema::hasColumn('settings', 'whatsapp_result_template_name')) {
                $table->string('whatsapp_result_template_name')->nullable();
            }
            if (! Schema::hasColumn('settings', 'whatsapp_result_language_code')) {
                $table->string('whatsapp_result_language_code')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_result_template_name', 'whatsapp_result_language_code']);
        });
    }
};

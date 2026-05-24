<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('show_logo')->default(false)->after('is_logo');
            $table->boolean('show_logo_only_whatsapp')->default(false)->after('show_logo');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['show_logo', 'show_logo_only_whatsapp']);
        });
    }
};



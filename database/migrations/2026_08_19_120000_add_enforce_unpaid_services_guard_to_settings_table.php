<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'enforce_unpaid_services_guard')) {
                $table->boolean('enforce_unpaid_services_guard')->default(true)->after('enforce_shift_hours');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'enforce_unpaid_services_guard')) {
                $table->dropColumn('enforce_unpaid_services_guard');
            }
        });
    }
};

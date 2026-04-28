<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'enforce_shift_hours')) {
                $table->boolean('enforce_shift_hours')->default(false)->after('discount_request_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'enforce_shift_hours')) {
                $table->dropColumn('enforce_shift_hours');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('child_test_devices', function (Blueprint $table) {
            $table->text('normal_range')->change();
        });
    }

    public function down(): void
    {
        Schema::table('child_test_devices', function (Blueprint $table) {
            $table->string('normal_range', 255)->change();
        });
    }
};

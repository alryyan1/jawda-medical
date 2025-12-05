<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_supervisor')->default(false)->after('is_nurse');
            // If you add workday_pattern_id, create that table first
            // $table->foreignId('workday_pattern_id')->nullable()->after('is_supervisor')->constrained('workday_patterns')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_supervisor');
            // $table->dropForeign(['workday_pattern_id']);
            // $table->dropColumn('workday_pattern_id');
        });
    }
};
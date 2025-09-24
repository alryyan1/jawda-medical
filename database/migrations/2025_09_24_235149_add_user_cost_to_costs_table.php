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
        Schema::table('costs', function (Blueprint $table) {
            if (!Schema::hasColumn('costs', 'user_cost')) {
                $table->unsignedBigInteger('user_cost')->nullable()->after('shift_id');
            }
            if (!Schema::hasForeignKey('costs', 'user_cost')) {
                $table->foreign('user_cost')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costs', function (Blueprint $table) {
            if (Schema::hasColumn('costs', 'user_cost')) {
                $table->dropForeign(['user_cost']);
                $table->dropColumn('user_cost');
            }
            if (Schema::hasColumn('costs', 'user_cost')) {
                $table->dropColumn('user_cost');
            }
        });
    }
};

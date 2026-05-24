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
        Schema::table('shifts', function (Blueprint $table) {
            if (!Schema::hasColumn('shifts', 'user_closed')) {
                $table->unsignedBigInteger('user_closed')->nullable();
                $table->index('user_closed');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            if (Schema::hasColumn('shifts', 'user_closed')) {
                // Attempt to drop the index if it exists
                try {
                    $table->dropIndex(['user_closed']);
                } catch (\Throwable $e) {
                    // ignore
                }
                $table->dropColumn('user_closed');
            }
        });
    }
};

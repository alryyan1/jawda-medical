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
        if (Schema::hasTable('labrequests') && Schema::hasColumn('labrequests', 'authorized_at')) {
            Schema::table('labrequests', function (Blueprint $table) {
                $table->dropColumn('authorized_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('labrequests', function (Blueprint $table) {
            $table->timestamp('authorized_at')->nullable();
        });
    }
};

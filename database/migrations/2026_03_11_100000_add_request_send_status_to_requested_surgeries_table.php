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
        Schema::table('requested_surgeries', function (Blueprint $table) {
            $table->boolean('request_send_status')->default(false)->after('approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requested_surgeries', function (Blueprint $table) {
            $table->dropColumn('request_send_status');
        });
    }
};

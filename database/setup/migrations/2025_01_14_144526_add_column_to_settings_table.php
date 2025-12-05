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
        Schema::table('settings', function (Blueprint $table) {
            $table->string('instance_id');
            $table->string('token');
            $table->boolean('send_result_after_auth');
            $table->boolean('send_result_after_result');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('instance_id');
            $table->dropColumn('token');
            $table->dropColumn('send_result_after_auth');
            $table->dropColumn('send_result_after_result');
        });
    }
};

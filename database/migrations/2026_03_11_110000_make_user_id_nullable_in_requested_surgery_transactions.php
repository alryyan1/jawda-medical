<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Allow null user_id for WhatsApp/system approvals.
     */
    public function up(): void
    {
        Schema::table('requested_surgery_transactions', function (Blueprint $table) {
            $table->dropForeign('rst_user_id_foreign');
        });
        Schema::table('requested_surgery_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
        Schema::table('requested_surgery_transactions', function (Blueprint $table) {
            $table->foreign('user_id', 'rst_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requested_surgery_transactions', function (Blueprint $table) {
            $table->dropForeign('rst_user_id_foreign');
        });
        Schema::table('requested_surgery_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
        Schema::table('requested_surgery_transactions', function (Blueprint $table) {
            $table->foreign('user_id', 'rst_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }
};

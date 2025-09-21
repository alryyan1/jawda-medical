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
        Schema::table('labrequests', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['sample_collected_by_user_id']);
            $table->dropForeign(['authorized_by_user_id']);
            $table->dropForeign(['payment_shift_id']);
            
            // Then drop the columns
            $table->dropColumn([
                'sample_collected_at',
                'sample_collected_by_user_id',
                'sample_id',
                'authorized_by_user_id',
                'payment_shift_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('labrequests', function (Blueprint $table) {
            // Recreate the columns
            $table->timestamp('sample_collected_at')->nullable();
            $table->unsignedBigInteger('sample_collected_by_user_id')->nullable();
            $table->string('sample_id')->nullable();
            $table->unsignedBigInteger('authorized_by_user_id')->nullable();
            $table->unsignedBigInteger('payment_shift_id')->nullable();
            
            // Recreate foreign key constraints
            $table->foreign('sample_collected_by_user_id')->references('id')->on('users');
            $table->foreign('authorized_by_user_id')->references('id')->on('users');
            $table->foreign('payment_shift_id')->references('id')->on('shifts');
        });
    }
};

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
        Schema::table('requested_results', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['entered_by_user_id']);
            $table->dropForeign(['authorized_by_user_id']);
            
            // Then drop the columns
            $table->dropColumn([
                'result_comment',
                'entered_by_user_id',
                'entered_at',
                'authorized_by_user_id',
                'authorized_at'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requested_results', function (Blueprint $table) {
            $table->text('result_comment')->nullable();
            $table->unsignedBigInteger('entered_by_user_id')->nullable();
            $table->timestamp('entered_at')->nullable();
            $table->unsignedBigInteger('authorized_by_user_id')->nullable();
            $table->timestamp('authorized_at')->nullable();
            
            // Recreate foreign key constraints
            $table->foreign('entered_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('authorized_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }
};

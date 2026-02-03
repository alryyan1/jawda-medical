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
        if (Schema::hasTable('labrequests')) {
            // Drop foreign key constraints only if their columns exist
            if (Schema::hasColumn('labrequests', 'sample_collected_by_user_id')) {
                Schema::table('labrequests', function (Blueprint $table) {
                    try { $table->dropForeign(['sample_collected_by_user_id']); } catch (\Throwable $e) {}
                });
            }
            if (Schema::hasColumn('labrequests', 'authorized_by_user_id')) {
                Schema::table('labrequests', function (Blueprint $table) {
                    try { $table->dropForeign(['authorized_by_user_id']); } catch (\Throwable $e) {}
                });
            }
            if (Schema::hasColumn('labrequests', 'payment_shift_id')) {
                Schema::table('labrequests', function (Blueprint $table) {
                    try { $table->dropForeign(['payment_shift_id']); } catch (\Throwable $e) {}
                });
            }

            // Drop columns individually if they exist
            $columnsToDrop = [
                'sample_collected_at',
                'sample_collected_by_user_id',
                'sample_id',
                'authorized_by_user_id',
                'payment_shift_id',
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('labrequests', $column)) {
                    Schema::table('labrequests', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
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

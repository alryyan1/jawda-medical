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
        if (Schema::hasTable('requested_service_deposit_deletions')) {
            return; // Table already exists
        }
        
        Schema::create('requested_service_deposit_deletions', function (Blueprint $table) {
            $table->id();

            // Original deposit reference
            $table->unsignedBigInteger('requested_service_deposit_id');

            // Snapshot of original deposit data
            $table->unsignedBigInteger('requested_service_id');
            $table->decimal('amount', 10, 2);
            $table->unsignedBigInteger('user_id'); // user who created the deposit
            $table->boolean('is_bank')->default(false);
            $table->boolean('is_claimed')->default(false);
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->timestamp('original_created_at')->nullable();

            // Deletion metadata
            $table->unsignedBigInteger('deleted_by')->nullable(); // user who deleted the deposit
            $table->timestamp('deleted_at')->nullable();

            $table->timestamps();

            $table->index('requested_service_id', 'rsdd_requested_service_id_idx');
            $table->index('requested_service_deposit_id', 'rsdd_deposit_id_idx');
            $table->index('user_id', 'rsdd_user_id_idx');
            $table->index('deleted_by', 'rsdd_deleted_by_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requested_service_deposit_deletions');
    }
};



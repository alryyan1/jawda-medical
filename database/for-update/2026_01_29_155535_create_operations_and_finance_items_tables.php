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
        // Create operations table
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admission_id')->nullable();
            $table->date('operation_date');
            $table->time('operation_time')->nullable();
            $table->string('operation_type');
            $table->text('description')->nullable();

            // Financial data
            $table->decimal('surgeon_fee', 15, 2)->default(0);
            $table->decimal('total_staff', 15, 2)->default(0);
            $table->decimal('total_center', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            // Payment tracking
            $table->decimal('cash_paid', 15, 2)->default(0);
            $table->decimal('bank_paid', 15, 2)->default(0);
            $table->string('bank_receipt_image')->nullable();

            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending, completed, cancelled
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        // Create operation_finance_items table
        Schema::create('operation_finance_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operation_id');
            $table->string('item_type'); // surgeon, assistant, anesthesia, center_share, consumables, equipment, radiology, accommodation
            $table->string('category'); // staff, center
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->boolean('is_auto_calculated')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_finance_items');
        Schema::dropIfExists('operations');
    }
};

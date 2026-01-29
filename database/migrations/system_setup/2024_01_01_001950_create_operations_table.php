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
        Schema::create('operations', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('admission_id')->nullable();
            $table->date('operation_date');
            $table->time('operation_time')->nullable();
            $table->string('operation_type', 255);
            $table->text('description')->nullable();
            $table->decimal('surgeon_fee', 15, 2)->default(0.00);
            $table->decimal('total_staff', 15, 2)->default(0.00);
            $table->decimal('total_center', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->decimal('cash_paid', 15, 2)->default(0.00);
            $table->decimal('bank_paid', 15, 2)->default(0.00);
            $table->string('bank_receipt_image', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 255)->default('pending');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};

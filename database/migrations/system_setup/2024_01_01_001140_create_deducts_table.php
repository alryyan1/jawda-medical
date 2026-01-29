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
        Schema::create('deducts', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('shift_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('payment_type_id')->default(1);
            $table->boolean('complete')->default(0);
            $table->string('total_amount_received')->default(0.00);
            $table->integer('number');
            $table->string('notes', 255)->nullable();
            $table->boolean('is_sell')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('is_postpaid')->default(0);
            $table->boolean('postpaid_complete')->default(0);
            $table->dateTime('postpaid_date')->nullable();
            $table->string('discount');
            $table->string('paid');
            $table->unsignedBigInteger('doctorvisit_id')->nullable();
            $table->double('endurance_percentage')->nullable();
            $table->unsignedBigInteger('user_paid')->nullable();
            $table->enum('payment_method', ["cash","bankak"]);
            $table->string('factory_serial_no', 255);
            $table->string('location', 255);
            $table->string('factory_number', 255);
            $table->foreign('payment_type_id', 'deducts_payment_type_id_foreign')
                  ->references('id')
                  ->on('payment_types')
                  ->onDelete('cascade');
            $table->foreign('shift_id', 'deducts_shift_id_foreign')
                  ->references('id')
                  ->on('shifts')
                  ->onDelete('cascade');
            $table->foreign('user_id', 'deducts_user_id_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deducts');
    }
};

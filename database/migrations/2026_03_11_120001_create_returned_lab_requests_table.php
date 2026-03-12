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
        Schema::create('returned_lab_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_request_id');
            $table->decimal('amount', 12, 2);
            $table->enum('returned_payment_method', ['cash', 'bank']);
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('lab_request_id')
                ->references('id')
                ->on('labrequests')
                ->onDelete('cascade');
            $table->foreign('user_id')
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
        Schema::dropIfExists('returned_lab_requests');
    }
};

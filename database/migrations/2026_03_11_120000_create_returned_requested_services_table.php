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
        Schema::create('returned_requested_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requested_service_id');
            $table->decimal('amount', 12, 2);
            $table->enum('returned_payment_method', ['cash', 'bank']);
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('requested_service_id')
                ->references('id')
                ->on('requested_services')
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
        Schema::dropIfExists('returned_requested_services');
    }
};

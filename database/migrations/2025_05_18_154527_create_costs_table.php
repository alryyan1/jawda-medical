<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('shift_id');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('cascade'); // Or restrict

            $table->unsignedBigInteger('user_cost')->nullable(); // User responsible for cost
            $table->foreign('user_cost')->references('id')->on('users')->onDelete('set null');

            $table->unsignedBigInteger('doctor_shift_id')->nullable();
            $table->foreign('doctor_shift_id')->references('id')->on('doctor_shifts')->onDelete('set null');

            $table->string('description')->nullable();
            $table->string('comment')->nullable();
            $table->decimal('amount', 11, 2); // Changed from int
            $table->decimal('amount_bankak', 11, 2)->default(0.00); // Changed from int

            $table->unsignedBigInteger('cost_category_id')->nullable();
            $table->foreign('cost_category_id')->references('id')->on('cost_categories')->onDelete('set null');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costs');
    }
};
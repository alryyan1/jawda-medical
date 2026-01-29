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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('ward_id');
            $table->string('room_number', 255);
            $table->enum('room_type', ["normal","vip"])->nullable();
            $table->decimal('price_per_day', 10, 2)->default(0.00);
            $table->integer('capacity')->default(1);
            $table->boolean('status')->default(1);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('ward_id', 'rooms_ward_id_foreign')
                  ->references('id')
                  ->on('wards')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};

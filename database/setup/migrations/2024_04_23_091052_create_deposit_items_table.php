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
        Schema::create('deposit_items', function (Blueprint $table) {
            $table->id();
            $table->integer('quantity')->default(0);
            $table->float('price',8,3)->default(0);
            $table->string('batch')->nullable();
            $table->date('expire')->nullable();
            $table->string('notes')->nullable();
            $table->string('barcode')->nullable();
            $table->boolean('return')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_items');
    }
};

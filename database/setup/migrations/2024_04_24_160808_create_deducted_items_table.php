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
        Schema::create('deducted_items', function (Blueprint $table) {
               $table->id();

            $table->integer('strips');
            $table->float('box');
            $table->integer('discount');
            $table->float('price',11,3);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deducted_items');
    }
};

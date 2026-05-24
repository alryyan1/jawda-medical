<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * e
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sub_specialists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('specialists_id');
            $table->timestamps();

            $table->foreign('specialists_id')
                  ->references('id')
                  ->on('specialists')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_specialists');
    }
};

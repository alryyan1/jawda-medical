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
        Schema::create('offered_tests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_test_id');
            $table->unsignedBigInteger('offer_id');
            $table->timestamps();
            
            $table->foreign('main_test_id')->references('id')->on('main_tests')->onDelete('cascade');
            $table->foreign('offer_id')->references('id')->on('offers')->onDelete('cascade');
            $table->unique(['main_test_id', 'offer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offered_tests');
    }
};

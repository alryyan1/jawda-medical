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
            $table->id('id');
            $table->unsignedBigInteger('main_test_id');
            $table->unsignedBigInteger('offer_id');
            $table->unsignedInteger('price')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['main_test_id', 'offer_id'], 'offered_tests_main_test_id_offer_id_unique');
            $table->foreign('main_test_id', 'offered_tests_main_test_id_foreign')
                  ->references('id')
                  ->on('main_tests')
                  ->onDelete('cascade');
            $table->foreign('offer_id', 'offered_tests_offer_id_foreign')
                  ->references('id')
                  ->on('offers')
                  ->onDelete('cascade');
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

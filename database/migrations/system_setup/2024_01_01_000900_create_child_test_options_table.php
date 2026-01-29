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
        Schema::create('child_test_options', function (Blueprint $table) {
            $table->id('id');
            $table->string('name', 255);
            $table->unsignedBigInteger('child_test_id');
            $table->foreign('child_test_id', 'child_test_options_child_test_id_foreign')
                  ->references('id')
                  ->on('child_tests')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('child_test_options');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_test_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            $table->unsignedBigInteger('child_test_id');
            $table->foreign('child_test_id')->references('id')->on('child_tests')->onDelete('cascade');

            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_test_options');
    }
};
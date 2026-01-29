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
        Schema::create('requested_organisms', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('lab_request_id');
            $table->string('organism', 255);
            $table->string('sensitive', 255)->nullable();
            $table->string('resistant', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requested_organisms');
    }
};

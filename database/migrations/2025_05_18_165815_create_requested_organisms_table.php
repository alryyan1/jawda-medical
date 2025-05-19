<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requested_organisms', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('lab_request_id');
            $table->foreign('lab_request_id')->references('id')->on('labrequests')->onDelete('cascade');

            $table->string('organism');
            $table->text('sensitive')->nullable(); // Using text for potentially long lists, made nullable
            $table->text('resistant')->nullable(); // Using text for potentially long lists, made nullable
            // No timestamps in original schema, but $table->timestamps() could be useful.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requested_organisms');
    }
};
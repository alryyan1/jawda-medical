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
        Schema::create('bankak_images', function (Blueprint $table) {
            $table->id('id');
            $table->string('image_url', 255);
            $table->unsignedBigInteger('doctorvisit_id')->nullable();
            $table->string('phone', 255);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('doctorvisit_id', 'bankak_images_doctorvisit_id_foreign')
                  ->references('id')
                  ->on('doctorvisits')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bankak_images');
    }
};

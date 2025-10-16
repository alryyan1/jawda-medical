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
            $table->id();
            $table->string('image_url');
            $table->unsignedBigInteger('doctorvisit_id')->nullable();
            $table->string('phone');
            $table->timestamps();
            
            // Add foreign key constraint for doctorvisit_id
            $table->foreign('doctorvisit_id')->references('id')->on('doctorvisits')->onDelete('set null');
            
            // Add index for better performance
            $table->index('phone');
            $table->index('doctorvisit_id');
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

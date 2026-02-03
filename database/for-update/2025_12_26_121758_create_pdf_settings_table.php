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
        Schema::create('pdf_settings', function (Blueprint $table) {
            $table->id();
            $table->string('font_family')->default('Amiri');
            $table->integer('font_size')->default(10);
            $table->string('logo_path')->nullable();
            $table->decimal('logo_width', 5, 2)->nullable();
            $table->decimal('logo_height', 5, 2)->nullable();
            $table->enum('logo_position', ['left', 'right'])->nullable();
            $table->string('hospital_name')->nullable();
            $table->string('header_image_path')->nullable();
            $table->string('footer_phone')->nullable();
            $table->text('footer_address')->nullable();
            $table->string('footer_email')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdf_settings');
    }
};

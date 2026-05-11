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
        Schema::table('settings', function (Blueprint $table) {
            $table->string('pdf_header_type')->default('logo')->nullable(); // logo, full_width, none
            $table->string('pdf_header_logo_position')->default('left')->nullable(); // left, right
            $table->integer('pdf_header_logo_width')->default(40)->nullable();
            $table->integer('pdf_header_logo_height')->default(40)->nullable();
            $table->integer('pdf_header_logo_x_offset')->default(5)->nullable();
            $table->integer('pdf_header_logo_y_offset')->default(5)->nullable();
            $table->integer('pdf_header_image_width')->default(200)->nullable();
            $table->integer('pdf_header_image_height')->default(30)->nullable();
            $table->integer('pdf_header_image_x_offset')->default(5)->nullable();
            $table->integer('pdf_header_image_y_offset')->default(5)->nullable();
            $table->string('pdf_header_title')->nullable();
            $table->string('pdf_header_subtitle')->nullable();
            $table->integer('pdf_header_title_font_size')->default(25)->nullable();
            $table->integer('pdf_header_subtitle_font_size')->default(17)->nullable();
            $table->integer('pdf_header_title_y_offset')->default(5)->nullable();
            $table->integer('pdf_header_subtitle_y_offset')->default(5)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'pdf_header_type',
                'pdf_header_logo_position',
                'pdf_header_logo_width',
                'pdf_header_logo_height',
                'pdf_header_logo_x_offset',
                'pdf_header_logo_y_offset',
                'pdf_header_image_width',
                'pdf_header_image_height',
                'pdf_header_image_x_offset',
                'pdf_header_image_y_offset',
                'pdf_header_title',
                'pdf_header_subtitle',
                'pdf_header_title_font_size',
                'pdf_header_subtitle_font_size',
                'pdf_header_title_y_offset',
                'pdf_header_subtitle_y_offset',
            ]);
        });
    }
};

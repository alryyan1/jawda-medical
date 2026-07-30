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
            if (! Schema::hasColumn('settings', 'pdf_header_type')) {
                $table->string('pdf_header_type', 255)->nullable()->default('logo');
            }
            if (! Schema::hasColumn('settings', 'pdf_header_logo_position')) {
                $table->string('pdf_header_logo_position', 255)->nullable()->default('left');
            }
            if (! Schema::hasColumn('settings', 'pdf_header_logo_width')) {
                $table->integer('pdf_header_logo_width')->nullable()->default(40);
            }
            if (! Schema::hasColumn('settings', 'pdf_header_logo_height')) {
                $table->integer('pdf_header_logo_height')->nullable()->default(40);
            }
            if (! Schema::hasColumn('settings', 'pdf_header_logo_x_offset')) {
                $table->integer('pdf_header_logo_x_offset')->nullable()->default(5);
            }
            if (! Schema::hasColumn('settings', 'pdf_header_logo_y_offset')) {
                $table->integer('pdf_header_logo_y_offset')->nullable()->default(5);
            }
            if (! Schema::hasColumn('settings', 'pdf_header_image_width')) {
                $table->integer('pdf_header_image_width')->nullable()->default(200);
            }
            if (! Schema::hasColumn('settings', 'pdf_header_image_height')) {
                $table->integer('pdf_header_image_height')->nullable()->default(30);
            }
            if (! Schema::hasColumn('settings', 'pdf_header_image_x_offset')) {
                $table->integer('pdf_header_image_x_offset')->nullable()->default(5);
            }
            if (! Schema::hasColumn('settings', 'pdf_header_image_y_offset')) {
                $table->integer('pdf_header_image_y_offset')->nullable()->default(5);
            }
            if (! Schema::hasColumn('settings', 'pdf_header_title')) {
                $table->string('pdf_header_title', 255)->nullable();
            }
            if (! Schema::hasColumn('settings', 'pdf_header_subtitle')) {
                $table->string('pdf_header_subtitle', 255)->nullable();
            }
            if (! Schema::hasColumn('settings', 'pdf_header_title_font_size')) {
                $table->integer('pdf_header_title_font_size')->nullable()->default(25);
            }
            if (! Schema::hasColumn('settings', 'pdf_header_subtitle_font_size')) {
                $table->integer('pdf_header_subtitle_font_size')->nullable()->default(17);
            }
            if (! Schema::hasColumn('settings', 'pdf_header_title_y_offset')) {
                $table->integer('pdf_header_title_y_offset')->nullable()->default(5);
            }
            if (! Schema::hasColumn('settings', 'pdf_header_subtitle_y_offset')) {
                $table->integer('pdf_header_subtitle_y_offset')->nullable()->default(5);
            }
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

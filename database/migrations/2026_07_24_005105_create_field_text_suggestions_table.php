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
        Schema::create('field_text_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('field_key');
            $table->string('phrase');
            $table->timestamps();

            $table->unique(['field_key', 'phrase']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_text_suggestions');
    }
};

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
        Schema::create('user_doc_selections', function (Blueprint $table) {
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('doc_id');
            $table->tinyInteger('active');
            $table->unsignedInteger('fav_service')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_doc_selections');
    }
};

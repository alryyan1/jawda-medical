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
            $table->integer('user_id', false, true);
            $table->integer('doc_id', false, true);
            $table->tinyInteger('active');
            $table->integer('fav_service', false, true)->nullable();
            
            // Composite primary key
            $table->primary(['user_id', 'doc_id']);
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

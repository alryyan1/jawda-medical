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
        Schema::create('items', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('section_id')->nullable();
            $table->string('name', 255);
            $table->integer('require_amount')->nullable()->default(0);
            $table->integer('initial_balance')->default(0);
            $table->integer('initial_price')->default(0);
            $table->integer('tests')->nullable()->default(0);
            $table->date('expire');
            $table->string('cost_price');
            $table->string('sell_price');
            $table->string('tax');
            $table->unsignedBigInteger('drug_category_id')->nullable();
            $table->unsignedBigInteger('pharmacy_type_id')->nullable();
            $table->string('barcode', 255)->nullable();
            $table->smallInteger('strips');
            $table->string('sc_name', 255);
            $table->string('market_name', 255);
            $table->string('batch', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('unit', 255)->default('');
            $table->string('active1', 255)->default('');
            $table->string('active2', 255)->default('');
            $table->string('active3', 255)->default('');
            $table->string('pack_size', 255)->default('');
            $table->string('approved_rp')->default(0.000);
            $table->unique(['barcode'], 'items_barcode_unique');
            $table->foreign('drug_category_id', 'items_drug_category_id_foreign')
                  ->references('id')
                  ->on('drug_categories')
                  ->onDelete('cascade');
            $table->foreign('pharmacy_type_id', 'items_pharmacy_type_id_foreign')
                  ->references('id')
                  ->on('pharmacy_types')
                  ->onDelete('cascade');
            $table->foreign('section_id', 'items_section_id_foreign')
                  ->references('id')
                  ->on('sections')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};

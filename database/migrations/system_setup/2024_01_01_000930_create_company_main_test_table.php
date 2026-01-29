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
        Schema::create('company_main_test', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('main_test_id');
            $table->unsignedBigInteger('company_id');
            $table->boolean('status');
            $table->string('price');
            $table->boolean('approve');
            $table->integer('endurance_static');
            $table->string('endurance_percentage');
            $table->boolean('use_static')->default(0);
            $table->unique(['main_test_id', 'company_id'], 'company_main_test_main_test_id_company_id_unique');
            $table->foreign('company_id', 'company_main_test_company_id_foreign')
                  ->references('id')
                  ->on('companies')
                  ->onDelete('cascade');
            $table->foreign('main_test_id', 'company_main_test_main_test_id_foreign')
                  ->references('id')
                  ->on('main_tests')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_main_test');
    }
};

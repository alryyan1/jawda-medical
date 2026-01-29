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
        Schema::create('main_tests', function (Blueprint $table) {
            $table->id('id');
            $table->string('main_test_name', 70);
            $table->text('conditions')->nullable();
            $table->integer('timer')->nullable();
            $table->boolean('hide_unit')->default(0);
            $table->boolean('is_special_test')->default(0);
            $table->integer('pack_id')->nullable();
            $table->boolean('pageBreak')->default(0);
            $table->integer('container_id')->default(1);
            $table->string('price')->nullable();
            $table->boolean('divided')->default(0);
            $table->boolean('available')->default(1);
            $table->foreign('container_id', 'cntainer_fk')
                  ->references('id')
                  ->on('containers')
                  ->onDelete('cascade');
            $table->foreign('pack_id', 'pakid_FK')
                  ->references('package_id')
                  ->on('packages')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('main_tests');
    }
};

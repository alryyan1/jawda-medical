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
        Schema::create('child_tests', function (Blueprint $table) {
            $table->id('id');
            $table->string('child_test_name', 70);
            $table->longText('json_params')->nullable();
            $table->double('low')->nullable();
            $table->double('upper')->nullable();
            $table->string('lower_limit')->nullable();
            $table->string('mean')->nullable();
            $table->string('upper_limit')->nullable();
            $table->unsignedBigInteger('main_test_id');
            $table->text('defval')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->text('normalRange')->nullable();
            $table->decimal('max', 8, 2)->nullable();
            $table->decimal('lowest', 8, 2)->nullable();
            $table->integer('test_order')->nullable();
            $table->unsignedBigInteger('child_group_id')->nullable();
            $table->foreign('child_group_id', 'child_tests_child_group_id_foreign')
                ->references('id')
                ->on('child_groups')
                ->onDelete('cascade');
            $table->foreign('unit_id', 'child_tests_unit_id_foreign')
                ->references('id')
                ->on('units')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('child_tests');
    }
};

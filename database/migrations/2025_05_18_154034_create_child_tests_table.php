<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_tests', function (Blueprint $table) {
            $table->id();
            $table->string('child_test_name', 70);
            $table->double('low')->nullable();
            $table->double('upper')->nullable();

            $table->unsignedBigInteger('main_test_id');
            $table->foreign('main_test_id')->references('id')->on('main_tests')->onDelete('cascade');

            $table->text('defval')->default('');

            $table->unsignedBigInteger('unit_id')->nullable();
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('set null');

            $table->text('normalRange')->default(''); // Consider a more specific name like 'normal_range_text' if it's free text
            $table->decimal('max', 8, 2)->nullable();
            $table->decimal('lowest', 8, 2)->nullable(); // 'lowest' seems like 'min', consider consistent naming like 'min_value' or 'lower_bound'
            $table->integer('test_order')->nullable();

            $table->unsignedBigInteger('child_group_id')->nullable();
            $table->foreign('child_group_id')->references('id')->on('child_groups')->onDelete('set null');

            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_tests');
    }
};
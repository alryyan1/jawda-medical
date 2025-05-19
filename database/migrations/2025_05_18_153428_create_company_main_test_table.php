<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_main_test', function (Blueprint $table) {
            $table->id(); // Standard for pivot with extra columns if you need to reference rows directly

            $table->unsignedBigInteger('main_test_id');
            $table->foreign('main_test_id')->references('id')->on('main_tests')->onDelete('cascade');

            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->boolean('status');
            $table->decimal('price', 8, 2);
            $table->boolean('approve');
            $table->integer('endurance_static');
            $table->decimal('endurance_percentage', 8, 2); // Changed from double
            $table->boolean('use_static')->default(false);

            // Timestamps are not in the original schema for this pivot table.
            // $table->timestamps(); // Add if needed

            // Optional: Add a unique constraint for the combination of main_test_id and company_id
            // if each test can only be associated with a company once.
            // $table->unique(['main_test_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_main_test');
    }
};
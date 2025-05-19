<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_statement_reports', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('income_statement_id');
            $table->foreign('income_statement_id')->references('id')->on('income_statements')->onDelete('cascade');

            // No timestamps in original schema, but $table->timestamps(); might be useful.
            // Consider adding other columns based on its purpose (e.g., report_type, generated_at, file_path)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_statement_reports');
    }
};
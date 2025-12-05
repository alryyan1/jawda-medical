<?php

use App\Models\IncomeStatement;
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
        Schema::create('income_statement_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(IncomeStatement::class);
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('income_statement_reports');
    }
};

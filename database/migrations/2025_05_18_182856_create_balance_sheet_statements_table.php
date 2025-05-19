<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_sheet_statements', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Balance Sheet as of 2023-12-31"

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict'); // Or set null

            $table->json('assets'); // JSON data for assets
            $table->json('obligations'); // JSON data for liabilities and equity

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_sheet_statements');
    }
};
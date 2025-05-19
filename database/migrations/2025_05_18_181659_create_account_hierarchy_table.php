<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_hierarchy', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id');
            $table->foreign('parent_id')->references('id')->on('finance_accounts')->onDelete('cascade');

            $table->unsignedBigInteger('child_id');
            $table->foreign('child_id')->references('id')->on('finance_accounts')->onDelete('cascade');

            $table->integer('level')->nullable(); // Depth of the child in the hierarchy from this parent

            $table->primary(['parent_id', 'child_id']); // Each child can only have one direct parent in this structure for a given parent
            // No timestamps
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_hierarchy');
    }
};
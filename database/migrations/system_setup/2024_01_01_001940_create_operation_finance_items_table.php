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
        Schema::create('operation_finance_items', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('operation_id');
            $table->string('item_type', 255);
            $table->string('category', 255);
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->boolean('is_auto_calculated')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_finance_items');
    }
};

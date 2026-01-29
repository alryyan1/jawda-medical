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
        Schema::create('account_hierarchy', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('child_id');
            $table->integer('level')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('child_id', 'account_hierarchy_child_id_foreign')
                  ->references('id')
                  ->on('finance_accounts')
                  ->onDelete('cascade');
            $table->foreign('parent_id', 'account_hierarchy_parent_id_foreign')
                  ->references('id')
                  ->on('finance_accounts')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_hierarchy');
    }
};

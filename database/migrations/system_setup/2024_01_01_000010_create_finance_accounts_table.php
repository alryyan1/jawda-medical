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
        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id('id');
            $table->string('name', 255);
            $table->unsignedBigInteger('account_category_id');
            $table->enum('debit', ["debit","credit"]);
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->enum('type', ["revenue","cost"])->nullable();
            $table->unique(['name'], 'finance_accounts_name_unique');
            $table->foreign('account_category_id', 'finance_accounts_account_category_id_foreign')
                  ->references('id')
                  ->on('account_categories')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_accounts');
    }
};

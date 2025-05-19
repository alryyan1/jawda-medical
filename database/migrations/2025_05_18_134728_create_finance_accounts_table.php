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
            $table->id(); // `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT
            $table->string('name'); // `name` varchar(255) NOT NULL
            $table->enum('debit', ['debit', 'credit']); // `debit` enum('debit','credit') NOT NULL
            $table->string('description')->nullable(); // `description` varchar(255) DEFAULT NULL
            $table->string('code'); // `code` varchar(255) NOT NULL (Consider if this should be unique)
            $table->enum('type', ['revenue', 'cost'])->nullable(); // `type` enum('revenue','cost') DEFAULT NULL
            $table->timestamps(); // `created_at` and `updated_at` timestamp NULL DEFAULT NULL
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

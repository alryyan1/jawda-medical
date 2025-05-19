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
        Schema::create('doctors', function (Blueprint $table) {
            $table->id(); // `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT
            $table->string('name'); // `name` varchar(255) NOT NULL
            $table->string('phone'); // `phone` varchar(255) NOT NULL
            $table->double('cash_percentage'); // `cash_percentage` double NOT NULL
            $table->double('company_percentage'); // `company_percentage` double NOT NULL
            $table->double('static_wage'); // `static_wage` double NOT NULL
            $table->double('lab_percentage'); // `lab_percentage` double NOT NULL

            // `specialist_id` bigint(20) UNSIGNED NOT NULL
            $table->unsignedBigInteger('specialist_id');
            // Assuming 'restrict' on delete is a safe default. Change if needed (e.g., 'cascade', 'set null').
            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('restrict');

            $table->integer('start'); // `start` int(11) NOT NULL
            $table->string('image')->nullable(); // `image` varchar(255) DEFAULT NULL

            // `finance_account_id` bigint(20) UNSIGNED DEFAULT NULL
            $table->unsignedBigInteger('finance_account_id')->nullable();
            $table->foreign('finance_account_id')->references('id')->on('finance_accounts')->onDelete('set null');

            // `finance_account_id_insurance` bigint(20) UNSIGNED NOT NULL (Using corrected name)
            $table->unsignedBigInteger('finance_account_id_insurance')->nullable();
            $table->foreign('finance_account_id_insurance', 'doctors_fin_acc_id_insurance_foreign')
                  ->references('id')->on('finance_accounts')->onDelete('set null');

            // `calc_insurance` tinyint(1) NOT NULL DEFAULT 0
            $table->boolean('calc_insurance')->default(false);

            $table->timestamps(); // `created_at` and `updated_at` timestamp NULL DEFAULT NULL
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
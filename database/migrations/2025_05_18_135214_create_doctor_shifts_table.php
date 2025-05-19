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
        Schema::create('doctor_shifts', function (Blueprint $table) {
            $table->id(); // `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade'); // Or 'restrict'

            $table->unsignedBigInteger('shift_id');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('cascade'); // Or 'restrict'

            $table->unsignedBigInteger('doctor_id');
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade'); // Or 'restrict'

            $table->boolean('status'); // `status` tinyint(1) NOT NULL
            $table->boolean('is_cash_revenue_prooved')->default(false); // is_cash_revenue_prooved
            $table->boolean('is_cash_reclaim_prooved')->default(false); // is_cash_reclaim_prooved
            $table->boolean('is_company_revenue_prooved')->default(false); // is_company_revenue_prooved
            $table->boolean('is_company_reclaim_prooved')->default(false); // is_company_reclaim_prooved

            $table->timestamps(); // `created_at` and `updated_at`
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_shifts');
    }
};
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
        Schema::create('company_service', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('company_id');
            $table->string('price');
            $table->string('static_endurance');
            $table->string('percentage_endurance');
            $table->string('static_wage');
            $table->string('percentage_wage');
            $table->boolean('use_static')->default(0);
            $table->boolean('approval')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['service_id', 'company_id'], 'company_service_service_id_company_id_unique');
            $table->foreign('company_id', 'company_service_company_id_foreign')
                  ->references('id')
                  ->on('companies')
                  ->onDelete('cascade');
            $table->foreign('service_id', 'company_service_service_id_foreign')
                  ->references('id')
                  ->on('services')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_service');
    }
};

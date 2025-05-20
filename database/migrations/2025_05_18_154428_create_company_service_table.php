<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_service', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('service_id');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');

            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->decimal('price', 8, 2);
            $table->decimal('static_endurance', 8, 2);
            $table->decimal('percentage_endurance', 8, 2);
            $table->decimal('static_wage', 8, 2);
            $table->decimal('percentage_wage', 8, 2);
            $table->boolean('use_static')->default(false);
            $table->boolean('approval')->default(false);
            $table->timestamps(); // Optional: if you want to track when the contract was created/updated
            // No timestamps in original schema
            // $table->unique(['service_id', 'company_id']); // Optional: if a service-company pair should be unique
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_service');
    }
};
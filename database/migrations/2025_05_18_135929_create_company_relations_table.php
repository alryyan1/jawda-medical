<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_relations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('lab_endurance', 8, 2);
            $table->decimal('service_endurance', 8, 2);

            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade'); // Or 'restrict'

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_relations');
    }
};
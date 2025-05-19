<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tooth_requested_services', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('requested_service_id');
            $table->foreign('requested_service_id')->references('id')->on('requested_services')->onDelete('cascade');

            $table->unsignedBigInteger('tooth_id');
            // FK to be added once 'teeth' table is defined
            // $table->foreign('tooth_id')->references('id')->on('teeth')->onDelete('cascade');

            $table->unsignedBigInteger('doctorvisit_id');
            $table->foreign('doctorvisit_id')->references('id')->on('doctorvisits')->onDelete('cascade');

            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tooth_requested_services');
    }
};
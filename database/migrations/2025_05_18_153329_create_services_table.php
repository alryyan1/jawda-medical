<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_group_id');
            $table->foreign('service_group_id')->references('id')->on('service_groups')->onDelete('cascade'); // Or restrict
            $table->string('name');
            $table->decimal('price', 11, 2); // `price` double(11,2) NOT NULL
            $table->boolean('activate')->default(false); // `activate` tinyint(1) NOT NULL DEFAULT 0
            $table->boolean('variable'); // `variable` tinyint(1) NOT NULL (No default specified)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};

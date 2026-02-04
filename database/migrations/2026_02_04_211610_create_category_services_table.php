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
        Schema::create('category_services', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Category::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(\App\Models\Service::class)->constrained()->onDelete('cascade');
            $table->decimal('percentage', 8, 2)->nullable();
            $table->decimal('fixed', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['category_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_services');
    }
};

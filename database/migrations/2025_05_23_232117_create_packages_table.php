<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            // Assuming package_id is the auto-incrementing primary key
            $table->id('package_id'); // Creates an auto-incrementing BIGINT primary key named 'package_id'
                                      // If you need INT(11), use $table->increments('package_id');

            $table->string('package_name', 50)->nullable()->unique(); // Package names should likely be unique
            
            // Regarding the 'container' column:
            // Option A: Simple string field as per your schema
            $table->string('container', 50)->comment('Default or specific container for the package');
            
            // Option B: If 'container' is a FK to your 'containers' table
            // $table->unsignedBigInteger('container_id')->nullable(); // Or whatever your containers.id type is
            // $table->foreign('container_id')->references('id')->on('containers')->onDelete('set null');
            // Then the column name in your schema would be 'container_id' not 'container' (VARCHAR)

            $table->integer('exp_time')->comment('Expiry time, e.g., in hours or days for sample stability');
            
            // Timestamps are not in your provided schema. Add if needed.
            // $table->timestamps(); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
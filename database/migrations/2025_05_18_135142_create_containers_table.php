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
        Schema::create('containers', function (Blueprint $table) {
            // `id` int(11) NOT NULL
            // Using $table->id() creates a BIGINT. If you specifically need INT, use $table->integer('id')->unsigned()->autoIncrement()->primary();
            // For consistency with Laravel's default $table->id() (BIGINT), I'll use that.
            // If strict INT(11) is required and this table will be referenced by BIGINT foreign keys, it's fine.
            // If other tables reference this `id` with INT foreign keys, then $table->increments('id'); is more appropriate for an INT.
            // Given other tables use BIGINT for IDs, $table->id() is generally preferred unless there's a strong reason for INT.
            $table->id(); // Creates UNSIGNED BIGINT auto-incrementing primary key. Change if strict INT(11) is required.
            // If you need INT(11) specifically:
            // $table->increments('id'); // This creates an unsigned INT auto-incrementing primary key.

            $table->string('container_name', 50); // `container_name` varchar(50) NOT NULL

            // This table does not have created_at/updated_at in the SQL.
            // If you want them: $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
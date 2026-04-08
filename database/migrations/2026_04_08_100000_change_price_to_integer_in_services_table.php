<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cast existing string values to integer before changing column type
        DB::statement('UPDATE services SET price = CAST(ROUND(CAST(price AS DECIMAL(10,2))) AS UNSIGNED) WHERE price IS NOT NULL AND price != ""');

        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('price')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('price')->default(null)->change();
        });
    }
};

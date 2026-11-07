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
        Schema::table('cost_categories', function (Blueprint $table) {
            $table->foreignId('service_group_id')
                ->nullable()
                ->after('name')
                ->constrained('service_groups')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cost_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_group_id');
        });
    }
};

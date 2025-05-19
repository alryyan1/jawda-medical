<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drugs_prescribed', function (Blueprint $table) {
            if (Schema::hasColumn('drugs_prescribed', 'medical_drug_route_id') && Schema::hasTable('medical_drug_routes')) {
                $table->foreign('medical_drug_route_id')
                      ->references('id')
                      ->on('medical_drug_routes')
                      ->onDelete('set null'); // Or 'restrict'
            }
        });
    }

    public function down(): void
    {
        Schema::table('drugs_prescribed', function (Blueprint $table) {
            if (Schema::hasColumn('drugs_prescribed', 'medical_drug_route_id')) {
                $table->dropForeign(['medical_drug_route_id']);
            }
        });
    }
};
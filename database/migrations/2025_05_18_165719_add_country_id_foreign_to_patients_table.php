<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'country_id') && Schema::hasTable('countries')) {
                $table->foreign('country_id')
                      ->references('id')
                      ->on('countries')
                      ->onDelete('set null'); // Or 'restrict'
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'country_id')) {
                $table->dropForeign(['country_id']); // Or $table->dropForeign('patients_country_id_foreign');
            }
        });
    }
};
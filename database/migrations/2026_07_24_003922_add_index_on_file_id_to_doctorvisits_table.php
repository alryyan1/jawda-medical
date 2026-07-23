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
        Schema::table('doctorvisits', function (Blueprint $table) {
            $table->index('file_id', 'doctorvisits_file_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctorvisits', function (Blueprint $table) {
            $table->dropIndex('doctorvisits_file_id_index');
        });
    }
};

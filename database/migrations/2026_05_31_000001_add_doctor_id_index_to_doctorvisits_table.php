<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctorvisits', function (Blueprint $table) {
            // doctor_id was missing an index — used in the most common filter
            $table->index('doctor_id', 'doctorvisits_doctor_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('doctorvisits', function (Blueprint $table) {
            $table->dropIndex('doctorvisits_doctor_id_index');
        });
    }
};

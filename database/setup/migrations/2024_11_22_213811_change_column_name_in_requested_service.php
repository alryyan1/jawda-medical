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
        Schema::table('requested_services', function (Blueprint $table) {
           // $table->renameColumn('doctor_visit_id','doctorvisits_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requested_services', function (Blueprint $table) {
         //   $table->renameColumn('doctorvisits_id','doctor_visit_id');

        });
    }
};

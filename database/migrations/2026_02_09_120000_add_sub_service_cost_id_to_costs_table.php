<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costs', function (Blueprint $table) {
            $table->unsignedBigInteger('sub_service_cost_id')->nullable()->after('doctor_shift_id_for_sub_cost');
            $table->foreign('sub_service_cost_id')->references('id')->on('sub_service_costs')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('costs', function (Blueprint $table) {
            $table->dropForeign(['sub_service_cost_id']);
            $table->dropColumn('sub_service_cost_id');
        });
    }
};

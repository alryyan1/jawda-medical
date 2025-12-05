<?php

use App\Models\Doctorvisit;
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
        Schema::table('tooth_requested_services', function (Blueprint $table) {
            $table->foreignIdFor(Doctorvisit::class);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tooth_requested_services', function (Blueprint $table) {
            // $table->dropForeign(['doctor_visit_id']);
            $table->dropForeignIdFor(Doctorvisit::class);
            // $table->dropColumn('doctor_visit_id');
        });
    }
};

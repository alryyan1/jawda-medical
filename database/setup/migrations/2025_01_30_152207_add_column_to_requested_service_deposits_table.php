<?php

use App\Models\Shift;
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
        Schema::table('requested_service_deposits', function (Blueprint $table) {
            $table->boolean('is_bank');
            $table->boolean('is_claimed');
            $table->foreignIdFor(Shift::class);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requested_service_deposits', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropColumn(['is_bank', 'is_claimed']);
        });
    }
};

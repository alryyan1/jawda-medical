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
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'social_status')) {
                $table->enum('social_status', ['single', 'married', 'widowed', 'divorced'])->nullable()->after('gender');
            }
            if (!Schema::hasColumn('patients', 'income_source')) {
                $table->string('income_source')->nullable()->after('address');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'social_status')) {
                $table->dropColumn('social_status');
            }
            if (Schema::hasColumn('patients', 'income_source')) {
                $table->dropColumn('income_source');
            }
        });
    }
};

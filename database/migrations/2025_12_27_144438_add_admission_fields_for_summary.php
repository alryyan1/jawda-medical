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
        Schema::table('admissions', function (Blueprint $table) {
            if (!Schema::hasColumn('admissions', 'provisional_diagnosis')) {
                $table->text('provisional_diagnosis')->nullable()->after('diagnosis');
            }
            if (!Schema::hasColumn('admissions', 'operations')) {
                $table->text('operations')->nullable()->after('provisional_diagnosis');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            if (Schema::hasColumn('admissions', 'provisional_diagnosis')) {
                $table->dropColumn('provisional_diagnosis');
            }
            if (Schema::hasColumn('admissions', 'operations')) {
                $table->dropColumn('operations');
            }
        });
    }
};

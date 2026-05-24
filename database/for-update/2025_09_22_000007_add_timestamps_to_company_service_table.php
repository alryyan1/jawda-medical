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
        if (!Schema::hasTable('company_service')) {
            return;
        }

        Schema::table('company_service', function (Blueprint $table) {
            if (!Schema::hasColumn('company_service', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('approval');
            }
            if (!Schema::hasColumn('company_service', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('company_service')) {
            return;
        }

        Schema::table('company_service', function (Blueprint $table) {
            if (Schema::hasColumn('company_service', 'updated_at')) {
                try { $table->dropColumn('updated_at'); } catch (\Throwable $e) {}
            }
            if (Schema::hasColumn('company_service', 'created_at')) {
                try { $table->dropColumn('created_at'); } catch (\Throwable $e) {}
            }
        });
    }
};



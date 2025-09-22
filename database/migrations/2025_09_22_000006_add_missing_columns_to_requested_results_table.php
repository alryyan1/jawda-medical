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
        if (!Schema::hasTable('requested_results')) {
            return;
        }

        // Add columns only if missing
        if (!Schema::hasColumn('requested_results', 'unit_id')) {
            Schema::table('requested_results', function (Blueprint $table) {
                $table->unsignedBigInteger('unit_id')->nullable()->after('updated_at');
            });
        }

        if (!Schema::hasColumn('requested_results', 'flags')) {
            Schema::table('requested_results', function (Blueprint $table) {
                $table->string('flags', 50)->nullable()->after('unit_id');
            });
        }

        if (!Schema::hasColumn('requested_results', 'result_comment')) {
            Schema::table('requested_results', function (Blueprint $table) {
                $table->text('result_comment')->nullable()->after('flags');
            });
        }

        if (!Schema::hasColumn('requested_results', 'entered_by_user_id')) {
            Schema::table('requested_results', function (Blueprint $table) {
                $table->unsignedBigInteger('entered_by_user_id')->nullable()->after('result_comment');
            });
        }

        if (!Schema::hasColumn('requested_results', 'entered_at')) {
            Schema::table('requested_results', function (Blueprint $table) {
                $table->timestamp('entered_at')->nullable()->after('entered_by_user_id');
            });
        }

        if (!Schema::hasColumn('requested_results', 'authorized_by_user_id')) {
            Schema::table('requested_results', function (Blueprint $table) {
                $table->unsignedBigInteger('authorized_by_user_id')->nullable()->after('entered_at');
            });
        }

        if (!Schema::hasColumn('requested_results', 'authorized_at')) {
            Schema::table('requested_results', function (Blueprint $table) {
                $table->timestamp('authorized_at')->nullable()->after('authorized_by_user_id');
            });
        }

        // Optional foreign keys if referenced tables exist (best-effort)
        if (Schema::hasColumn('requested_results', 'unit_id') && Schema::hasTable('units')) {
            Schema::table('requested_results', function (Blueprint $table) {
                try { $table->foreign('unit_id')->references('id')->on('units'); } catch (\Throwable $e) {}
            });
        }
        if (Schema::hasColumn('requested_results', 'entered_by_user_id') && Schema::hasTable('users')) {
            Schema::table('requested_results', function (Blueprint $table) {
                try { $table->foreign('entered_by_user_id')->references('id')->on('users'); } catch (\Throwable $e) {}
            });
        }
        if (Schema::hasColumn('requested_results', 'authorized_by_user_id') && Schema::hasTable('users')) {
            Schema::table('requested_results', function (Blueprint $table) {
                try { $table->foreign('authorized_by_user_id')->references('id')->on('users'); } catch (\Throwable $e) {}
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('requested_results')) {
            return;
        }

        // Drop FKs then columns if they exist
        foreach ([
            'authorized_by_user_id',
            'entered_by_user_id',
            'unit_id',
        ] as $fkCol) {
            if (Schema::hasColumn('requested_results', $fkCol)) {
                Schema::table('requested_results', function (Blueprint $table) use ($fkCol) {
                    try { $table->dropForeign([$fkCol]); } catch (\Throwable $e) {}
                });
            }
        }

        foreach ([
            'authorized_at',
            'authorized_by_user_id',
            'entered_at',
            'entered_by_user_id',
            'result_comment',
            'flags',
            'unit_id',
        ] as $col) {
            if (Schema::hasColumn('requested_results', $col)) {
                Schema::table('requested_results', function (Blueprint $table) use ($col) {
                    try { $table->dropColumn($col); } catch (\Throwable $e) {}
                });
            }
        }
    }
};



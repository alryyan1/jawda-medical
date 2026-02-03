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
        if (!Schema::hasTable('patients')) {
            return;
        }

        // Add file_id if missing
        if (!Schema::hasColumn('patients', 'file_id')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->unsignedBigInteger('file_id')->nullable()->after('id');
            });

            // Optionally add FK if files table exists
            if (Schema::hasTable('files')) {
                Schema::table('patients', function (Blueprint $table) {
                    try { $table->foreign('file_id')->references('id')->on('files'); } catch (\Throwable $e) {}
                });
            }
        }

        // Add sample_collect_time if missing
        if (!Schema::hasColumn('patients', 'sample_collect_time')) {
            Schema::table('patients', function (Blueprint $table) {
                // Position after sample_collected if exists, else after result_is_locked
                if (Schema::hasColumn('patients', 'sample_collected')) {
                    $table->time('sample_collect_time')->nullable()->after('sample_collected');
                } else {
                    $table->time('sample_collect_time')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('patients')) {
            return;
        }

        // Drop FK first then column for file_id
        if (Schema::hasColumn('patients', 'file_id')) {
            Schema::table('patients', function (Blueprint $table) {
                try { $table->dropForeign(['file_id']); } catch (\Throwable $e) {}
            });
            Schema::table('patients', function (Blueprint $table) {
                try { $table->dropColumn('file_id'); } catch (\Throwable $e) {}
            });
        }

        // Drop sample_collect_time if exists
        if (Schema::hasColumn('patients', 'sample_collect_time')) {
            Schema::table('patients', function (Blueprint $table) {
                try { $table->dropColumn('sample_collect_time'); } catch (\Throwable $e) {}
            });
        }
    }
};



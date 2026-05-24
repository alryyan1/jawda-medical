<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('doctorvisits')) {
            return;
        }

        // Add only the columns missing in altamayoz compared to asnan
        if (!Schema::hasColumn('doctorvisits', 'visit_date')) {
            Schema::table('doctorvisits', function (Blueprint $table) {
                $table->date('visit_date')->nullable()->after('file_id');
            });
            try {
                DB::table('doctorvisits')->whereNull('visit_date')->update([
                    'visit_date' => DB::raw('DATE(COALESCE(created_at, CURRENT_DATE))'),
                ]);
                DB::statement('ALTER TABLE doctorvisits MODIFY visit_date date NOT NULL');
            } catch (\Throwable $e) {}
        }

        if (!Schema::hasColumn('doctorvisits', 'visit_time')) {
            Schema::table('doctorvisits', function (Blueprint $table) {
                $table->time('visit_time')->nullable()->after('visit_date');
            });
        }

        if (!Schema::hasColumn('doctorvisits', 'status')) {
            Schema::table('doctorvisits', function (Blueprint $table) {
                $table->string('status', 255)->default('waiting')->after('visit_time');
            });
        }

        if (!Schema::hasColumn('doctorvisits', 'visit_type')) {
            Schema::table('doctorvisits', function (Blueprint $table) {
                $table->string('visit_type', 255)->nullable()->after('status');
            });
        }

        if (!Schema::hasColumn('doctorvisits', 'queue_number')) {
            Schema::table('doctorvisits', function (Blueprint $table) {
                $table->integer('queue_number')->nullable()->after('visit_type');
            });
        }

        if (!Schema::hasColumn('doctorvisits', 'reason_for_visit')) {
            Schema::table('doctorvisits', function (Blueprint $table) {
                $table->text('reason_for_visit')->nullable()->after('queue_number');
            });
        }

        if (!Schema::hasColumn('doctorvisits', 'visit_notes')) {
            Schema::table('doctorvisits', function (Blueprint $table) {
                $table->text('visit_notes')->nullable()->after('reason_for_visit');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('doctorvisits')) {
            return;
        }

        $columns = [
            'visit_date',
            'visit_time',
            'status',
            'visit_type',
            'queue_number',
            'reason_for_visit',
            'visit_notes',
        ];

        // Drop columns if they exist
        foreach ($columns as $col) {
            if (Schema::hasColumn('doctorvisits', $col)) {
                Schema::table('doctorvisits', function (Blueprint $table) use ($col) {
                    try { $table->dropColumn($col); } catch (\Throwable $e) {}
                });
            }
        }
    }
};



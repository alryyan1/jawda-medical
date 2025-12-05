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
        Schema::table('doctor_shifts', function (Blueprint $table) {
            // Add new columns
            if (!Schema::hasColumn('doctor_shifts', 'start_time')) {
                $table->timestamp('start_time')->nullable()->after('status')->comment('Actual start time of the doctor working');
            }
            if (!Schema::hasColumn('doctor_shifts', 'end_time')) {
                $table->timestamp('end_time')->nullable()->after('start_time')->comment('Actual end time of the doctor working');
            }

            // Modify 'status' column to add default and comment
            // Ensure the column type matches the existing type if only changing default/comment.
            // If the type was just `boolean` or `tinyInteger(1)` without explicit NOT NULL and default,
            // Laravel might require a `change()` call.
            // If 'status' was already `tinyint(1) NOT NULL`, we might only need to add default.
            // However, to be safe and cover cases, using change() is often better.
            if (Schema::hasColumn('doctor_shifts', 'status')) {
                $table->integer('status')->default(1)->comment('Is this doctor shift session currently active?')->change();
            }


            // Columns to remove
            $columnsToRemove = [
                'is_cash_revenue_journal_generated',
                'is_insurance_revenue_journal_generated',
                'is_doctor_cash_reclaim_journal_generated',
                'is_doctor_insurance_reclaim_journal_generated'
            ];

            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('doctor_shifts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_shifts', function (Blueprint $table) {
            // Drop new columns
            if (Schema::hasColumn('doctor_shifts', 'start_time')) {
                $table->dropColumn('start_time');
            }
            if (Schema::hasColumn('doctor_shifts', 'end_time')) {
                $table->dropColumn('end_time');
            }

            // Revert 'status' column changes (remove default, comment)
            if (Schema::hasColumn('doctor_shifts', 'status')) {
                // Default and comment are often metadata and might not need explicit removal
                // depending on DB, but for clarity:
                $table->tinyInteger('status')->comment(null)->default(null)->change(); // Or set back to original default if known
            }

            // Re-add removed columns
            if (!Schema::hasColumn('doctor_shifts', 'is_cash_revenue_journal_generated')) {
                $table->tinyInteger('is_cash_revenue_journal_generated')->default(0);
            }
            if (!Schema::hasColumn('doctor_shifts', 'is_insurance_revenue_journal_generated')) {
                $table->tinyInteger('is_insurance_revenue_journal_generated')->default(0);
            }
            if (!Schema::hasColumn('doctor_shifts', 'is_doctor_cash_reclaim_journal_generated')) {
                $table->tinyInteger('is_doctor_cash_reclaim_journal_generated')->default(0);
            }
            if (!Schema::hasColumn('doctor_shifts', 'is_doctor_insurance_reclaim_journal_generated')) {
                $table->tinyInteger('is_doctor_insurance_reclaim_journal_generated')->default(0);
            }
        });
    }
};
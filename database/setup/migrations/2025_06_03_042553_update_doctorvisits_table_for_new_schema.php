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
        // It's good practice to ensure doctrine/dbal is installed
        // composer require doctrine/dbal

        // Drop unique key first if it exists and involves columns being changed to nullable
        // Need to know the exact name of the unique key from your old schema.
        // Example: If the key was named 'doctorvisits_patient_id_doctor_shift_id_unique'
        // This is tricky as discovering constraint names programmatically can be DB specific.
        // It's often safer to handle this manually via SQL if needed before running the migration,
        // or by being very specific if Laravel generated the name predictably.
        // For now, we'll assume Laravel will handle it or it will be dropped when 'doctor_shift_id' is changed.


        Schema::table('doctorvisits', function (Blueprint $table) {
            // --- Make existing columns nullable ---
            if (Schema::hasColumn('doctorvisits', 'doctor_shift_id')) {
                $table->unsignedBigInteger('doctor_shift_id')->nullable()->change();
            }
            if (Schema::hasColumn('doctorvisits', 'file_id')) {
                $table->unsignedBigInteger('file_id')->nullable()->change();
            }

            // --- Add new columns (check existence first) ---
            if (!Schema::hasColumn('doctorvisits', 'doctor_id')) {
                $table->foreignId('doctor_id')->after('patient_id');
            }
            if (!Schema::hasColumn('doctorvisits', 'user_id')) {
                // Assuming user_id references the user who created the visit
                $table->foreignId('user_id')->after('doctor_id');
            }
            if (!Schema::hasColumn('doctorvisits', 'visit_date')) {
                // Attempt to place it logically, e.g., after file_id or doctor_shift_id
                $table->date('visit_date')->after('file_id'); // Default NOT NULL as per new schema
            }
            if (!Schema::hasColumn('doctorvisits', 'visit_time')) {
                $table->time('visit_time')->nullable()->after('visit_date');
            }
            if (!Schema::hasColumn('doctorvisits', 'status')) {
                $table->string('status')->default('waiting')->after('visit_time');
            }
            if (!Schema::hasColumn('doctorvisits', 'visit_type')) {
                $table->string('visit_type')->nullable()->after('status')->comment('e.g., New, Follow-up, Emergency, Consultation');
            }
            if (!Schema::hasColumn('doctorvisits', 'queue_number')) {
                $table->integer('queue_number')->nullable()->after('visit_type');
            }
            if (!Schema::hasColumn('doctorvisits', 'reason_for_visit')) {
                $table->text('reason_for_visit')->nullable()->after('queue_number');
            }
            if (!Schema::hasColumn('doctorvisits', 'visit_notes')) {
                $table->text('visit_notes')->nullable()->after('reason_for_visit');
            }

            // --- Modify existing columns for defaults/comments ---
            if (Schema::hasColumn('doctorvisits', 'is_new')) {
                $table->integer('is_new')->default(1)->comment('Is this a new patient visit or a follow-up for an existing issue?')->change();
            }
            if (Schema::hasColumn('doctorvisits', 'number')) {
                $table->integer('number')->default(0)->comment('Original "number" column, clarify purpose. Queue number?')->change();
            }
            if (Schema::hasColumn('doctorvisits', 'only_lab')) {
                $table->integer('only_lab')->default(0)->comment('Is this visit solely for lab work without doctor consultation?')->change();
            }

            // Ensure shift_id and file_id foreign keys are correctly set up if they weren't explicitly.
            // The new DDL shows them. If your old schema didn't have these FKs explicitly,
            // you might need to add them here.
            // Example:
            // if (Schema::hasColumn('doctorvisits', 'shift_id') && !$this->hasForeignKey('doctorvisits', 'doctorvisits_shift_id_foreign')) {
            //     $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('cascade'); // or set null
            // }
            // if (Schema::hasColumn('doctorvisits', 'file_id') && !$this->hasForeignKey('doctorvisits', 'doctorvisits_file_id_foreign')) {
            //    $table->foreign('file_id')->references('id')->on('files')->onDelete('set null');
            // }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctorvisits', function (Blueprint $table) {
            // Drop new columns
            if (Schema::hasColumn('doctorvisits', 'visit_notes')) $table->dropColumn('visit_notes');
            if (Schema::hasColumn('doctorvisits', 'reason_for_visit')) $table->dropColumn('reason_for_visit');
            if (Schema::hasColumn('doctorvisits', 'queue_number')) $table->dropColumn('queue_number');
            if (Schema::hasColumn('doctorvisits', 'visit_type')) $table->dropColumn('visit_type');
            if (Schema::hasColumn('doctorvisits', 'status')) $table->dropColumn('status');
            if (Schema::hasColumn('doctorvisits', 'visit_time')) $table->dropColumn('visit_time');
            if (Schema::hasColumn('doctorvisits', 'visit_date')) $table->dropColumn('visit_date');
            
            // Drop foreign keys before columns if they were added for new columns
            if (Schema::hasColumn('doctorvisits', 'user_id')) {
                $table->dropForeign(['user_id']); // Assumes default naming convention userd by constrained()
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('doctorvisits', 'doctor_id')) {
                $table->dropForeign(['doctor_id']);
                $table->dropColumn('doctor_id');
            }


            // Revert existing columns to their original state (NOT NULL)
            if (Schema::hasColumn('doctorvisits', 'doctor_shift_id')) {
                // You might need to handle cases where data is now NULL before making it NOT NULL
                // DB::table('doctorvisits')->whereNull('doctor_shift_id')->update(['doctor_shift_id' => 0]); // Example default if applicable
                $table->unsignedBigInteger('doctor_shift_id')->nullable(false)->change();
            }
            if (Schema::hasColumn('doctorvisits', 'file_id')) {
                // DB::table('doctorvisits')->whereNull('file_id')->update(['file_id' => 0]); // Example default if applicable
                $table->unsignedBigInteger('file_id')->nullable(false)->change();
            }

            // Revert comments and defaults
            if (Schema::hasColumn('doctorvisits', 'is_new')) {
                $table->integer('is_new')->default(1)->comment(null)->change();
            }
            if (Schema::hasColumn('doctorvisits', 'number')) {
                $table->integer('number')->default(null)->comment(null)->change(); // Assuming original had no default
            }
            if (Schema::hasColumn('doctorvisits', 'only_lab')) {
                $table->integer('only_lab')->default(0)->comment(null)->change();
            }

            // Re-add unique constraint if it was dropped (complex, name dependent)
            // if (!$this->hasUniqueKey('doctorvisits', 'doctorvisits_patient_id_doctor_shift_id_unique')) {
            //     $table->unique(['patient_id', 'doctor_shift_id'], 'doctorvisits_patient_id_doctor_shift_id_unique_old');
            // }
        });
    }

    // Helper to check for foreign key existence (simplified)
    // protected function hasForeignKey($table, $fkName)
    // {
    //     $conn = Schema::getConnection()->getDoctrineSchemaManager();
    //     $foreignKeys = $conn->listTableForeignKeys($table);
    //     foreach ($foreignKeys as $foreignKey) {
    //         if ($foreignKey->getName() === $fkName) {
    //             return true;
    //         }
    //     }
    //     return false;
    // }
    // Helper to check for unique key existence (simplified)
    // protected function hasUniqueKey($table, $indexName)
    // {
    //     $conn = Schema::getConnection()->getDoctrineSchemaManager();
    //     $indexes = $conn->listTableIndexes($table);
    //     foreach ($indexes as $index) {
    //         if ($index->isUnique() && !$index->isPrimary() && $index->getName() === $indexName) {
    //             return true;
    //         }
    //     }
    //     return false;
    // }
};
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
        // Ensure doctrine/dbal is installed
        // composer require doctrine/dbal

        Schema::table('doctor_services', function (Blueprint $table) {
            // 1. Rename 'fixed_amount' to 'fixed' if it exists
            if (Schema::hasColumn('doctor_services', 'fixed_amount') && !Schema::hasColumn('doctor_services', 'fixed')) {
                $table->renameColumn('fixed_amount', 'fixed');
            }

       

            // 4. Ensure timestamps exist (they were added in a specific migration in the new schema)
            if (!Schema::hasColumn('doctor_services', 'created_at') || !Schema::hasColumn('doctor_services', 'updated_at')) {
                $table->timestamps(); // Adds nullable created_at and updated_at
            }

            // 5. Address Unique Constraint:
            // The new schema DDL doesn't explicitly show a unique constraint on (doctor_id, service_id).
            // If the old schema had one (e.g., 'doctor_service_unique') and it's NO LONGER needed,
            // you would drop it here.
            // If it IS needed, and was perhaps dropped by renaming or changing columns, re-add it.
            // For this example, let's assume the unique constraint on (doctor_id, service_id) IS still desired.
            // First, drop any potentially existing old one if its name is known or to avoid conflict if re-adding with a new name.
            // $table->dropUnique('doctor_service_unique'); // If this was the old name
            // Then, add it with Laravel's conventional naming if it doesn't exist:
            // This check is complex as index names can vary.
            // A simple way if you want to ensure it exists:
            // try { $table->unique(['doctor_id', 'service_id']); } catch (\Exception $e) {} // May fail if already exists with different name
            
            // If you need to be specific about the constraint for (doctor_id, service_id):
         
            // The new DDL implicitly relies on individual FK indexes.
            // If the unique(doctor_id, service_id) is no longer a requirement per the new DDL, no action needed here.
            // If it IS a requirement but just not shown in the DDL's index list, you'd add:
            // $table->unique(['doctor_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::table('doctor_services', function (Blueprint $table) {
            // Revert 'fixed' to 'fixed_amount'
            if (Schema::hasColumn('doctor_services', 'fixed') && !Schema::hasColumn('doctor_services', 'fixed_amount')) {
                $table->renameColumn('fixed', 'fixed_amount');
            }

           
            

            // If timestamps were added by this migration's up() method (because they were truly missing)
            // then drop them. If they always existed, this part is not needed.
            // if (Schema::hasColumn('doctor_services', 'created_at') && Schema::hasColumn('doctor_services', 'updated_at')) {
            //    $table->dropTimestamps();
            // }

            // Re-add old unique constraint if it was dropped and is part of old schema's logic.
            // $table->unique(['doctor_id', 'service_id'], 'doctor_service_unique_old');
        });
    }
};
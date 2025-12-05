<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB; // Import DB facade
use Illuminate\Support\Facades\Schema; // Import Schema facade

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure the 'companies' table and 'status' column exist
        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'status')) {
            DB::table('companies')->update(['status' => 1]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Reversing this specific data update is tricky because the previous 'status'
     * values for each row could have been different (0 or 1).
     * If you need to revert, you'd typically restore from a backup or
     * have a specific state to revert all rows to (e.g., all to 0).
     * For this example, the down() method will comment out the action,
     * as we don't know the individual previous states.
     */
    public function down(): void
    {
        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'status')) {
            // Option 1: Do nothing on rollback if previous states were mixed.
            // This is generally the safest if you don't have a universal "inactive" state to revert to.

            // Option 2: Set all statuses back to 0 (inactive) if that's a desired universal rollback state.
            // DB::table('companies')->update(['status' => 0]);
            // WARNING: This will make ALL companies inactive on rollback, regardless of their previous state.

            // For this migration, we'll log that no automatic data change is performed on rollback.
            // If you need to revert data, please do so manually or from a backup.
            // \Illuminate\Support\Facades\Log::info("Rolling back 'set_all_companies_status_to_active' migration. 'status' column for 'companies' table not automatically changed back.");
        }
    }
};
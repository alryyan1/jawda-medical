<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint; // Not strictly needed for data-only
use Illuminate\Support\Facades\Schema;   // Not strictly needed for data-only
use Illuminate\Support\Facades\DB;       // Import DB facade

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure the 'activate' column exists in the 'services' table
        if (Schema::hasTable('services') && Schema::hasColumn('services', 'activate')) {
            DB::table('services')->update(['activate' => 1]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Reversing this might mean setting 'activate' back to its previous state.
     * If all were 0 before, or if there's no specific previous state to restore to,
     * setting them all to 0 (inactive) or doing nothing are options.
     * For this example, we'll assume we might want to set them back to 0,
     * but this depends heavily on your application's logic and previous data state.
     */
    public function down(): void
    {
        if (Schema::hasTable('services') && Schema::hasColumn('services', 'activate')) {
            // Option 1: Do nothing, as the previous state might have been mixed.
            // No action needed if you don't want to mass-deactivate on rollback.

            // Option 2: Set all back to 0 (inactive) if that's a desired rollback state.
            // Be cautious with this if the previous states were varied.
            // DB::table('services')->update(['activate' => 0]);

            // Option 3: If you had a way to track previous states (e.g., a backup or another column),
            // you would restore those values here. This is generally too complex for a simple migration.

            // For this example, we will choose to do nothing on rollback, as we don't know the previous individual states.
            // If you have a specific state to revert to (like all being 0), uncomment the line above.
            // Log::info("Rolling back 'set_all_services_active' migration. 'activate' column not changed back automatically.");
        }
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Import DB facade for default update

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add the is_active column, typically after other boolean flags or user details
            // Defaulting to true (1) for existing users, assuming they are active.
            // Adjust the default if new users should be inactive by default.
            $table->boolean('is_active')->default(true)->after('is_supervisor'); 
            // You can add an index if you frequently query/filter by this column
            // $table->index('is_active');
        });

        // Optionally, update existing users to be active if you want to ensure it.
        // This is generally safe if all current users are considered active.
        // If you have a specific way to determine active status for existing users,
        // you might want to run a seeder or a more complex update query.
        // For now, the default(true) handles new records and records created before this migration
        // might need manual update or if your DB default for boolean is 0, they'd be inactive.
        // To be explicit for existing records:
        if (DB::getDriverName() !== 'sqlite') { // update() doesn't work well with default values on SQLite in migrations
             DB::table('users')->update(['is_active' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
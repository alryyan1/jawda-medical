<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure doctrine/dbal is installed
        // composer require doctrine/dbal

        // Optional: Drop the old unique key `uniqe_pid_maintest_requests` if it's no longer needed.
        // This depends on your business logic. The new schema DDL doesn't show it.
        // You MUST confirm the actual constraint name from your DB using `SHOW CREATE TABLE labrequests;`
        // if ($this->hasIndex('labrequests', 'uniqe_pid_maintest_requests')) { // Custom helper needed
        // try {
        //     DB::statement('ALTER TABLE labrequests DROP INDEX uniqe_pid_maintest_requests');
        // } catch (\Illuminate\Database\QueryException $e) {
        //     // Log or handle if index doesn't exist or name is different
        //     Log::warning("Could not drop index 'uniqe_pid_maintest_requests' on labrequests: " . $e->getMessage());
        // }
        // }

        Schema::table('labrequests', function (Blueprint $table) {
            // Change data types for boolean-like columns
         
        
            // Add new sample_id column
            if (!Schema::hasColumn('labrequests', 'sample_id')) {
                // Placing it after 'is_paid' for logical grouping, can be adjusted
                $table->string('sample_id')->nullable()->unique()->after('is_paid')->comment('Unique ID for the sample collected for this test');
            }

            // doctor_visit_id and done should already exist from previous migrations on the old schema.
            // If their type or nullability needs to match new DDL exactly (e.g. default on done):
            if (Schema::hasColumn('labrequests', 'done')) {
                $table->tinyInteger('done')->default(0)->change(); // Ensures default matches new DDL
            }
            if (!Schema::hasColumn('labrequests', 'doctor_visit_id')) {

                $table->integer('doctor_visit_id');
                // Already nullable from old migration, ensure FK is correct
                // (usually handled by original adding migration)
            }
        });
    }

    public function down(): void
    {
        Schema::table('labrequests', function (Blueprint $table) {
            if (Schema::hasColumn('labrequests', 'sample_id')) {
                // $table->dropUnique('labrequests_sample_id_unique'); // Unique constraint on sample_id
                $table->dropColumn('sample_id');
            }

            // Revert boolean-like columns to original int(11) if that was truly the old state
       
            // ... repeat for is_lab2lab, valid, no_sample, is_bankak

            // Revert price and amount_paid to double
            if (Schema::hasColumn('labrequests', 'price')) {
                DB::statement('ALTER TABLE labrequests MODIFY price DOUBLE PRECISION(10,1) DEFAULT 0.0');
            }
            if (Schema::hasColumn('labrequests', 'amount_paid')) {
                 DB::statement('ALTER TABLE labrequests MODIFY amount_paid DOUBLE PRECISION(10,1) DEFAULT 0.0');
            }

            // Revert 'done' default if it was different
            if (Schema::hasColumn('labrequests', 'done')) {
                $table->tinyInteger('done')->default(0)->change(); // Assuming old default was also 0
            }

            // Re-add the old unique key `uniqe_pid_maintest_requests` if it was dropped
            // if (!$this->hasIndex('labrequests', 'uniqe_pid_maintest_requests')) { // Custom helper needed
            //    $table->unique(['main_test_id', 'pid'], 'uniqe_pid_maintest_requests');
            // }
        });
    }

    // You might need a helper to check for index existence by name if you want to conditionally drop/add
    // protected function hasIndex(string $table, string $indexName): bool
    // {
    //     $connection = Schema::getConnection();
    //     $doctrineSchemaManager = $connection->getDoctrineSchemaManager();
    //     $databasePlatform = $doctrineSchemaManager->getDatabasePlatform();
    //     $database = $connection->getDatabaseName();

    //     $indexes = $doctrineSchemaManager->listTableIndexes($table);

    //     return isset($indexes[strtolower($indexName)]); // Index names might be case-insensitive depending on DB
    // }
};
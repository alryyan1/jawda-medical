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
        Schema::table('users', function (Blueprint $table) {
            // Ensure the column exists and is of the correct type (unsignedBigInteger, nullable)
            // This was defined in the initial create_users_table migration.
            // If it wasn't, you'd define it here before adding the foreign key.
            // $table->unsignedBigInteger('doctor_id')->nullable()->after('updated_at'); // Example if not already created

            if (Schema::hasColumn('users', 'doctor_id')) { // Check if column exists
                $table->foreign('doctor_id')
                      ->references('id')
                      ->on('doctors')
                      ->onDelete('set null'); // Or 'restrict', 'cascade' as per your rules
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'doctor_id')) { // Check before trying to drop
                // Convention for foreign key name: tablename_columnname_foreign
                $table->dropForeign(['doctor_id']); // Or $table->dropForeign('users_doctor_id_foreign');
            }
        });
    }
};
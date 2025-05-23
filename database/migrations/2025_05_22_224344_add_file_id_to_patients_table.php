<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_file_id_to_patients_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'file_id')) { // Check if column exists
                $table->foreignId('file_id')
                      ->nullable() // Or not nullable if every patient MUST have a file immediately
                      ->after('id') // Or wherever you prefer
                      ->constrained('files') // Assuming your files table is named 'files'
                      ->onDelete('set null'); // Or 'restrict' or 'cascade' based on your rules
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'file_id')) {
                // To drop foreign key, you need to know its conventional name or specify it
                // Default: tablename_columnname_foreign -> patients_file_id_foreign
                $table->dropForeign(['file_id']);
                $table->dropColumn('file_id');
            }
        });
    }
};
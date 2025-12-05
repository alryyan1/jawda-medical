<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_sample_collected_at_to_labrequests_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labrequests', function (Blueprint $table) {
            $table->timestamp('sample_collected_at')->nullable()->after('sample_id');
            $table->foreignId('sample_collected_by_user_id')->nullable()->after('sample_collected_at')
                  ->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('labrequests', function (Blueprint $table) {
            // $table->dropForeign(['sample_collected_by_user_id']); // If named constraint
            $table->dropColumn(['sample_collected_at', 'sample_collected_by_user_id']);
        });
    }
};
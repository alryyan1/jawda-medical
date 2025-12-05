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
        Schema::table('requested_results', function (Blueprint $table) {
            // Add columns after 'unit_id' or choose appropriate placement
            $table->string('flags', 50)->nullable()->after('unit_id');
            $table->text('result_comment')->nullable()->after('flags');
            
            $table->foreignId('entered_by_user_id')->nullable()->after('result_comment')
                  ->constrained('users')->onDelete('set null');
            $table->timestamp('entered_at')->nullable()->after('entered_by_user_id');
            
            $table->foreignId('authorized_by_user_id')->nullable()->after('entered_at')
                  ->constrained('users')->onDelete('set null');
            $table->timestamp('authorized_at')->nullable()->after('authorized_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requested_results', function (Blueprint $table) {
            // Drop foreign keys first if they were explicitly named,
            // otherwise Laravel handles it by convention (tablename_columnname_foreign)
            // Example: $table->dropForeign('requested_results_entered_by_user_id_foreign');
            //          $table->dropForeign('requested_results_authorized_by_user_id_foreign');

            $table->dropColumn([
                'flags',
                'result_comment',
                'entered_by_user_id',
                'entered_at',
                'authorized_by_user_id',
                'authorized_at'
            ]);
        });
    }
};
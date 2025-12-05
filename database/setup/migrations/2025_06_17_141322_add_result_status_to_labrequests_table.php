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
        Schema::table('labrequests', function (Blueprint $table) {
            // Define the possible statuses for a lab request's results
            // Adjust these statuses based on your actual workflow needs
            $table->string('result_status')->default('pending_sample')->after('no_sample');
            // Common statuses might include:
            // - pending_sample (sample not yet collected/received)
            // - sample_received (sample received, pending entry)
            // - pending_entry (synonym for sample_received or if sample step is implicit)
            // - results_partial (some results entered, but not all)
            // - results_complete_pending_auth (all results entered, awaiting authorization)
            // - authorized (all results entered and authorized)
            // - cancelled
            // You could also use an ENUM if your database supports it and you prefer stricter values:
            // $table->enum('result_status', [
            //     'pending_sample',
            //     'sample_received',
            //     'pending_entry',
            //     'results_partial',
            //     'results_complete_pending_auth',
            //     'authorized',
            //     'cancelled'
            // ])->default('pending_sample')->after('no_sample');

            // Add other fields that were mentioned in the controller logic if they don't exist
            // but were not explicitly in your shared table schemas.
            // For example, if LabRequestController's update method was trying to save these:
            if (!Schema::hasColumn('labrequests', 'authorized_by_user_id')) {
                $table->foreignId('authorized_by_user_id')->nullable()->constrained('users')->onDelete('set null')->after('result_status');
            }
            if (!Schema::hasColumn('labrequests', 'authorized_at')) {
                $table->timestamp('authorized_at')->nullable()->after('authorized_by_user_id');
            }
            if (!Schema::hasColumn('labrequests', 'payment_shift_id')) { // For recordPayment method
                 $table->foreignId('payment_shift_id')->nullable()->constrained('shifts')->onDelete('set null')->after('authorized_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('labrequests', function (Blueprint $table) {
            $table->dropColumn('result_status');
            if (Schema::hasColumn('labrequests', 'authorized_by_user_id')) {
                // Need to drop foreign key before column if it was created
                // $table->dropForeign(['authorized_by_user_id']); // Eloquent names it users_authorized_by_user_id_foreign
                $table->dropColumn('authorized_by_user_id');
            }
            if (Schema::hasColumn('labrequests', 'authorized_at')) {
                $table->dropColumn('authorized_at');
            }
            if (Schema::hasColumn('labrequests', 'payment_shift_id')) {
                // $table->dropForeign(['payment_shift_id']);
                $table->dropColumn('payment_shift_id');
            }
        });
    }
};
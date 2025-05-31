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
        Schema::create('audited_requested_services', function (Blueprint $table) {
            $table->id();

            $table->foreignId('audited_patient_record_id')->constrained('audited_patient_records')->onDelete('cascade');
            $table->foreignId('original_requested_service_id')->nullable()->constrained('requested_services')->onDelete('set null'); // Link to the original, nullable if auditor adds a new service not in original
            $table->foreignId('service_id')->constrained('services')->onDelete('restrict'); // The actual service

            $table->decimal('audited_price', 15, 2); // Price as determined/verified by auditor
            $table->integer('audited_count')->default(1);
            $table->decimal('audited_discount_per', 5, 2)->nullable()->default(0); // Percentage discount
            $table->decimal('audited_discount_fixed', 15, 2)->nullable()->default(0); // Fixed amount discount
            $table->decimal('audited_endurance', 15, 2)->default(0); // What the company is approved to cover by auditor

            $table->enum('audited_status', [
                'pending_review',       // Initial status when copied or added by auditor
                'approved_for_claim',   // Auditor approves this item for the insurance claim
                'rejected_by_auditor',  // Auditor rejects this item for the claim
                'needs_edits',          // Auditor flags for further correction before approval (less common for this table, more for parent record)
                'cancelled_by_auditor' // If auditor decides to cancel it entirely from the claim
            ])->default('pending_review');
            
            $table->text('auditor_notes_for_service')->nullable(); // Specific notes for this service line item

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audited_requested_services');
    }
};
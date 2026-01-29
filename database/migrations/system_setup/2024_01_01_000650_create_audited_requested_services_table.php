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
            $table->id('id');
            $table->unsignedBigInteger('audited_patient_record_id');
            $table->unsignedBigInteger('original_requested_service_id')->nullable();
            $table->unsignedBigInteger('service_id');
            $table->decimal('audited_price', 15, 2);
            $table->integer('audited_count')->default(1);
            $table->decimal('audited_discount_per', 5, 2)->default(0.00);
            $table->decimal('audited_discount_fixed', 15, 2)->default(0.00);
            $table->decimal('audited_endurance', 15, 2)->default(0.00);
            $table->enum('audited_status', ["pending_review","approved_for_claim","rejected_by_auditor","needs_edits","cancelled_by_auditor"])->default('pending_review');
            $table->text('auditor_notes_for_service')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('audited_patient_record_id', 'audited_requested_services_audited_patient_record_id_foreign')
                  ->references('id')
                  ->on('audited_patient_records')
                  ->onDelete('cascade');
            $table->foreign('original_requested_service_id', 'audited_requested_services_original_requested_service_id_foreign')
                  ->references('id')
                  ->on('requested_services')
                  ->onDelete('cascade');
            $table->foreign('service_id', 'audited_requested_services_service_id_foreign')
                  ->references('id')
                  ->on('services')
                  ->onDelete('cascade');
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

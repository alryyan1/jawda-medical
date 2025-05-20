<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// database/migrations/xxxx_xx_xx_xxxxxx_create_doctor_visits_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctorvisits', function (Blueprint $table) {
            $table->id(); // DoctorVisit ID

            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('doctor_id')->constrained('doctors')->onDelete('restrict'); // The doctor for this specific visit

            // User who created this visit entry (e.g., receptionist, or system if auto-created)
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict'); 
            
            // The general clinic shift this visit belongs to
            $table->foreignId('shift_id')->constrained('shifts')->onDelete('restrict'); 

            // Optional: Link to a specific doctor's working session if you have DoctorShift model/table
            $table->foreignId('doctor_shift_id')->nullable()->constrained('doctor_shifts')->onDelete('set null');

            // Optional: Link to an appointment if visits are scheduled
            // $table->foreignId('appointment_id')->nullable()->unique()->constrained('appointments')->onDelete('set null');

            // Optional: Link to a "file" if using your `files` table as a master record for encounters
            $table->foreignId('file_id')->nullable()->constrained('files')->onDelete('set null');
            
            $table->date('visit_date');
            $table->time('visit_time')->nullable(); // Or appointment_time if linked to appointments

            // Status of the visit
            $table->string('status')->default('waiting'); // e.g., waiting, with_doctor, lab_pending, imaging_pending, payment_pending, completed, cancelled, no_show
            
            $table->string('visit_type')->nullable()->comment('e.g., New, Follow-up, Emergency, Consultation');
            $table->integer('queue_number')->nullable();
            
            // Clinical summary if not stored directly on patient or separate notes table
            $table->text('reason_for_visit')->nullable(); // Or chief_complaint
            $table->text('visit_notes')->nullable(); // General notes by doctor or nurse for this visit specifically

            // Boolean flags from your original 'doctorvisits' schema
            $table->boolean('is_new')->default(true)->comment('Is this a new patient visit or a follow-up for an existing issue?'); // Or derive from patient's visit_count
            $table->integer('number')->default(0)->comment('Original "number" column, clarify purpose. Queue number?'); // Your schema had `NOT NULL`
            $table->boolean('only_lab')->default(false)->comment('Is this visit solely for lab work without doctor consultation?');


            // Financials related to this specific visit, if not handled by summing requested_services
            // $table->decimal('total_charges', 15, 2)->default(0.00);
            // $table->decimal('total_paid', 15, 2)->default(0.00);
            // $table->boolean('is_fully_paid')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_visits');
    }
};
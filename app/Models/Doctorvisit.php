<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorVisit extends Model
{
    use HasFactory;

    // If your table name is 'doctorvisits' (plural) as per some of your earlier schemas:
    // protected $table = 'doctorvisits'; 

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'patient_id',
        'doctor_id',    // The doctor attending this specific visit
        'doctor_shift_id', // If the visit is tied to a specific doctor's shift session
        'user_id',      // User who created/managed this visit (e.g., receptionist, nurse)
        'shift_id',     // The general clinic shift this visit occurred in
        'file_id',      // If visits are associated with a "file" from the `files` table
        'visit_date',   // In your `appointments` table this was `appointment_date`
        'visit_time',   // In your `appointments` table this was `appointment_time`
        'status',       // e.g., 'waiting', 'with_doctor', 'completed', 'cancelled', 'no_show'
        'notes',        // General notes for the visit
        'is_new',       // From your `doctorvisits` table schema (boolean)
        'number',       // From your `doctorvisits` table schema (integer, perhaps a queue number)
        'only_lab',     // From your `doctorvisits` table schema (boolean)
        // Add any other fields from your 'doctor_visits' table schema
        // e.g., 'visit_type', 'reason_for_visit', 'vital_signs_id', etc.
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'visit_date' => 'date',
        'visit_time' => 'datetime:H:i:s', // Casts to Carbon instance, formats to H:i:s on toArray/json
        'is_new' => 'boolean',
        'only_lab' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    /**
     * Get the patient that this visit belongs to.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the doctor who attended this visit.
     */
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Get the doctor's shift session this visit belongs to (if applicable).
     */
    public function doctorShift()
    {
        return $this->belongsTo(DoctorShift::class); // Assuming DoctorShift model exists
    }

    /**
     * Get the user (e.g., receptionist) who created/managed this visit.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the clinic shift this visit belongs to.
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Get the file associated with this visit (if any).
     */
    public function file()
    {
        return $this->belongsTo(File::class); // Assuming File model exists
    }

    /**
     * Get the appointment associated with this visit (if one visit per appointment).
     */
    public function appointment()
    {
        // Assuming 'doctorvisit_id' is the FK in the 'appointments' table
        return $this->hasOne(Appointment::class); 
        // If Appointment has doctor_visit_id, then use belongsTo if the FK is on DoctorVisit table.
        // Check your appointments table schema for the FK.
    }

    /**
     * Get all requested services for this visit.
     */
    public function requestedServices()
    {
        // Assuming your `requested_services` table has `doctorvisits_id` (or `doctor_visit_id`)
        // If the FK in requested_services is `doctorvisits_id` (plural):
        return $this->hasMany(RequestedService::class, 'doctorvisits_id');
        // If the FK is `doctor_visit_id` (singular):
        // return $this->hasMany(RequestedService::class, 'doctor_visit_id');
    }

    // You might also have relationships to:
    // - LabRequests (if directly linked to a visit)
    // - Prescriptions (if a prescription is tied to a specific visit)
    // - Vitals (if vitals are stored per visit)
}
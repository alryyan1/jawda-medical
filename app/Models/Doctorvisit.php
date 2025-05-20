<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorVisit extends Model
{
    use HasFactory;

    // If your table name is 'doctorvisits' (plural)
    protected $table = 'doctorvisits';

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'user_id',
        'shift_id',
        'doctor_shift_id',
        // 'appointment_id',
        'file_id',
        'visit_date',
        'visit_time',
        'status',
        'visit_type',
        'queue_number',
        'reason_for_visit',
        'visit_notes',
        'is_new', // from original schema
        'number', // from original schema
        'only_lab', // from original schema
    ];

    protected $casts = [
        'visit_date' => 'date',
        // 'visit_time' => 'datetime:H:i:s', // If storing as TIME, Laravel might handle it without explicit cast. If DATETIME, use 'datetime'
        'is_new' => 'boolean',
        'only_lab' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * User who created/managed this visit entry (e.g., receptionist).
     */
    public function createdByUser() // Renamed to avoid conflict if a 'user' is the patient/doctor
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The general clinic shift this visit belongs to.
     */
    public function generalShift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    /**
     * The specific doctor's working session, if applicable.
     */
    public function doctorShift()
    {
        return $this->belongsTo(DoctorShift::class);
    }

    /**
     * The appointment linked to this visit, if any.
     * public function appointment() {
     *     return $this->belongsTo(Appointment::class); // Or hasOne if FK is on appointments table
     * }
     */
    
    /**
     * The "file" associated with this visit, if any.
     */
    public function file()
    {
        return $this->belongsTo(File::class); // Assuming File model exists
    }


    /**
     * Get all requested services for this visit.
     */
    public function requestedServices()
    {
        // Adjust FK name if your requested_services table uses 'doctor_visit_id'
        return $this->hasMany(RequestedService::class, 'doctorvisits_id'); 
    }

    /**
     * Get all lab requests associated with this visit.
     * This assumes lab_requests has a 'doctor_visit_id' foreign key.
     * If lab_requests are only linked to patient, this relation might not be direct.
     * public function labRequests() {
     *     return $this->hasMany(LabRequest::class);
     * }
     */
    
    /**
     * Get all prescriptions issued during this visit.
     * This assumes drugs_prescribed has a 'doctor_visit_id' foreign key.
     * public function prescriptions() {
     *     return $this->hasMany(DrugPrescribed::class);
     * }
     */

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('visit_date', today());
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    public function scopeWithDoctor($query)
    {
        return $query->where('status', 'with_doctor');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
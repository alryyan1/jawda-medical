<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Admission extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'ward_id',
        'room_id',
        'bed_id',
        'admission_date',
        'admission_time',
        'discharge_date',
        'discharge_time',
        'admission_type',
        'admission_reason',
        'diagnosis',
        'status',
        'doctor_id',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'admission_date' => 'date',
        'discharge_date' => 'date',
        'admission_time' => 'datetime',
        'discharge_time' => 'datetime',
    ];

    /**
     * Get the patient that owns the admission.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the ward for the admission.
     */
    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * Get the room for the admission.
     */
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the bed for the admission.
     */
    public function bed()
    {
        return $this->belongsTo(Bed::class);
    }

    /**
     * Get the doctor for the admission.
     */
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Get the user who created the admission.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include active admissions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'admitted');
    }

    /**
     * Scope a query to only include discharged admissions.
     */
    public function scopeDischarged($query)
    {
        return $query->where('status', 'discharged');
    }
}

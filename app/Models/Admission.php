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
        'specialist_doctor_id',
        'user_id',
        'notes',
        'provisional_diagnosis',
        'operations',
        'medical_history',
        'current_medications',
        'referral_source',
        'expected_discharge_date',
        'next_of_kin_name',
        'next_of_kin_relation',
        'next_of_kin_phone',
    ];

    protected $casts = [
        'admission_date' => 'date',
        'discharge_date' => 'date',
        'expected_discharge_date' => 'date',
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
     * Get the specialist doctor for the admission.
     */
    public function specialistDoctor()
    {
        return $this->belongsTo(Doctor::class, 'specialist_doctor_id');
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

    /**
     * Get the requested services for the admission.
     */
    public function requestedServices()
    {
        return $this->hasMany(AdmissionRequestedService::class);
    }

    /**
     * Get the requested lab tests for the admission.
     */
    public function requestedLabTests()
    {
        return $this->hasMany(AdmissionRequestedLabTest::class);
    }

    /**
     * Get the vital signs for the admission.
     */
    public function vitalSigns()
    {
        return $this->hasMany(AdmissionVitalSign::class);
    }

    /**
     * Get all transactions for the admission.
     */
    public function transactions()
    {
        return $this->hasMany(AdmissionTransaction::class);
    }

    /**
     * Calculate the number of days the patient has been admitted.
     * If discharged, calculate from admission_date to discharge_date.
     * If still admitted, calculate from admission_date to current date.
     * Minimum is 1 day (if admitted today, it counts as 1 day).
     */
    public function getDaysAdmittedAttribute()
    {
        $startDate = $this->admission_date;
        $endDate = $this->discharge_date ?? now()->toDateString();

        $days = $startDate->diffInDays($endDate);

        // If same day or difference is 0, return 1 (minimum 1 day)
        // Otherwise add 1 to include both start and end days
        return max(1, $days + 1);
    }

    /**
     * Calculate the total balance for the admission.
     * Balance = Total Credits - Total Debits
     */
    public function getBalanceAttribute()
    {
        $totalCredits = (float) $this->transactions()->where('type', 'credit')->sum('amount');
        $totalDebits = (float) $this->transactions()->where('type', 'debit')->sum('amount');

        return $totalCredits - $totalDebits;
    }
}

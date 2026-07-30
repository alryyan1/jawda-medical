<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitVital extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_visit_id',
        'patient_id',
        'recorded_by_user_id',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'temperature',
        'heart_rate',
        'respiratory_rate',
        'pain_scale',
        'spo2',
        'weight',
        'height',
        'rbs',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function doctorVisit(): BelongsTo
    {
        return $this->belongsTo(DoctorVisit::class, 'doctor_visit_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}

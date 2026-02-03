<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionDose extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_id',
        'medication_name',
        'dosage',
        'frequency',
        'route',
        'start_date',
        'end_date',
        'instructions',
        'notes',
        'doctor_id',
        'user_id',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the admission that owns the dose.
     */
    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    /**
     * Get the doctor who prescribed the dose.
     */
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Get the user who created the dose record.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include active doses.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

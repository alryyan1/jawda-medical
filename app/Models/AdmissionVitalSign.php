<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionVitalSign extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_id',
        'user_id',
        'reading_date',
        'reading_time',
        'temperature',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'oxygen_saturation',
        'oxygen_flow',
        'pulse_rate',
        'notes',
    ];

    protected $casts = [
        'reading_date' => 'date',
        'reading_time' => 'datetime',
        'temperature' => 'decimal:2',
        'blood_pressure_systolic' => 'integer',
        'blood_pressure_diastolic' => 'integer',
        'oxygen_saturation' => 'decimal:2',
        'oxygen_flow' => 'decimal:2',
        'pulse_rate' => 'integer',
    ];

    /**
     * Get the admission that owns the vital sign.
     */
    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    /**
     * Get the user who recorded the vital sign.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

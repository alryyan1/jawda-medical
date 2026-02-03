<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionTreatment extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_id',
        'treatment_plan',
        'treatment_details',
        'notes',
        'user_id',
        'treatment_date',
        'treatment_time',
    ];

    protected $casts = [
        'treatment_date' => 'date',
        'treatment_time' => 'datetime',
    ];

    /**
     * Get the admission that owns the treatment.
     */
    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    /**
     * Get the user who created the treatment record.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

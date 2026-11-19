<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitIntakeOutput extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_visit_id',
        'patient_id',
        'recorded_by_user_id',
        'recorded_at',
        'feeding',
        'iv_fluid',
        'maintenance',
        'urine_output',
        'insensible_loss',
        'notes',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'feeding' => 'decimal:2',
        'iv_fluid' => 'decimal:2',
        'maintenance' => 'decimal:2',
        'urine_output' => 'decimal:2',
        'insensible_loss' => 'decimal:2',
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

    public function totalIntake(): float
    {
        return (float) $this->feeding + (float) $this->iv_fluid + (float) $this->maintenance;
    }

    public function totalOutput(): float
    {
        return (float) $this->urine_output + (float) $this->insensible_loss;
    }

    public function balance(): float
    {
        return $this->totalIntake() - $this->totalOutput();
    }
}

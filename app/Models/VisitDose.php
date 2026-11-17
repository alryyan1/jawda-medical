<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitDose extends Model
{
    use HasFactory;

    public const STATUS_GIVEN = 'given';

    public const STATUS_NOT_GIVEN = 'not_given';

    public const STATUS_POSTPONED = 'postponed';

    protected $fillable = [
        'doctor_visit_id',
        'patient_id',
        'administered_by_user_id',
        'medication_name',
        'dosage',
        'status',
        'administered_at',
        'notes',
    ];

    protected $casts = [
        'administered_at' => 'datetime',
    ];

    public function doctorVisit(): BelongsTo
    {
        return $this->belongsTo(DoctorVisit::class, 'doctor_visit_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function administeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by_user_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_GIVEN => 'تم إعطاؤها',
            self::STATUS_NOT_GIVEN => 'لم تُعطَ',
            self::STATUS_POSTPONED => 'مؤجلة',
            default => $this->status,
        };
    }
}

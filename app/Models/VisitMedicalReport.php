<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitMedicalReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_visit_id',
        'user_id',
        'content',
        'complete',
        'completed_at',
        'is_printed',
        'printed_by_user_id',
    ];

    protected $casts = [
        'complete' => 'boolean',
        'completed_at' => 'datetime',
        'is_printed' => 'boolean',
    ];

    public function doctorVisit(): BelongsTo
    {
        return $this->belongsTo(DoctorVisit::class, 'doctor_visit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function printedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by_user_id');
    }
}

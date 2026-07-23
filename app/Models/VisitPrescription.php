<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitPrescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_visit_id',
        'user_id',
        'notes',
        'is_printed',
        'printed_by_user_id',
        'printed_at',
    ];

    protected $casts = [
        'is_printed' => 'boolean',
        'printed_at' => 'datetime',
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

    public function items(): HasMany
    {
        return $this->hasMany(VisitPrescriptionItem::class)->orderBy('sort_order');
    }
}

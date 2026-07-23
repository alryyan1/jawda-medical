<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitPrescriptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_prescription_id',
        'medication_name',
        'dosage',
        'frequency',
        'duration',
        'route',
        'instructions',
        'sort_order',
    ];

    public function visitPrescription(): BelongsTo
    {
        return $this->belongsTo(VisitPrescription::class);
    }
}

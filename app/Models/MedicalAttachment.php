<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'doctor_visit_id',
        'uploaded_by_user_id',
        'category',
        'title',
        'original_filename',
        'file_path',
        'mime_type',
        'file_size',
        'note',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctorVisit(): BelongsTo
    {
        return $this->belongsTo(DoctorVisit::class, 'doctor_visit_id');
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}

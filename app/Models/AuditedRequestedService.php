<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditedRequestedService extends Model
{
    use HasFactory;

    protected $fillable = [
        'audited_patient_record_id', 'original_requested_service_id', 'service_id',
        'audited_price', 'audited_count', 'audited_discount_per', 'audited_discount_fixed',
        'audited_endurance', 'audited_status', 'auditor_notes_for_service',
    ];

    protected $casts = [
        'audited_price' => 'decimal:2',
        'audited_count' => 'integer',
        'audited_discount_per' => 'decimal:2',
        'audited_discount_fixed' => 'decimal:2',
        'audited_endurance' => 'decimal:2',
    ];

    public function auditedPatientRecord() { return $this->belongsTo(AuditedPatientRecord::class); }
    public function originalRequestedService() { return $this->belongsTo(RequestedService::class, 'original_requested_service_id'); }
    public function service() { return $this->belongsTo(Service::class); }
}
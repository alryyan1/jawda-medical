<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $audited_patient_record_id
 * @property int|null $original_requested_service_id
 * @property int $service_id
 * @property string $audited_price
 * @property int $audited_count
 * @property string $audited_discount_per
 * @property string $audited_discount_fixed
 * @property string $audited_endurance
 * @property string $audited_status
 * @property string|null $auditor_notes_for_service
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AuditedPatientRecord $auditedPatientRecord
 * @property-read \App\Models\RequestedService|null $originalRequestedService
 * @property-read \App\Models\Service $service
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedRequestedService newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedRequestedService newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedRequestedService query()
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedRequestedService whereAuditedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedRequestedService whereAuditedDiscountFixed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedRequestedService whereAuditedDiscountPer($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedRequestedService whereAuditedEndurance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedRequestedService whereAuditedPatientRecordId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedRequestedService whereAuditedPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedRequestedService whereAuditedStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedRequestedService whereAuditorNotesForService($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedRequestedService whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedRequestedService whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedRequestedService whereOriginalRequestedServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedRequestedService whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedRequestedService whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
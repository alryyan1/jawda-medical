<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $patient_id
 * @property int $doctor_visit_id
 * @property int|null $audited_by_user_id
 * @property \Illuminate\Support\Carbon|null $audited_at
 * @property string $status
 * @property string|null $auditor_notes
 * @property array|null $original_patient_data_snapshot
 * @property string|null $edited_patient_name
 * @property string|null $edited_phone
 * @property string|null $edited_gender
 * @property int|null $edited_age_year
 * @property int|null $edited_age_month
 * @property int|null $edited_age_day
 * @property string|null $edited_address
 * @property int|null $edited_doctor_id
 * @property string|null $edited_insurance_no
 * @property \Illuminate\Support\Carbon|null $edited_expire_date
 * @property string|null $edited_guarantor
 * @property int|null $edited_subcompany_id
 * @property int|null $edited_company_relation_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AuditedRequestedService> $auditedRequestedServices
 * @property-read int|null $audited_requested_services_count
 * @property-read \App\Models\User|null $auditor
 * @property-read \App\Models\DoctorVisit $doctorVisit
 * @property-read \App\Models\CompanyRelation|null $editedCompanyRelation
 * @property-read \App\Models\Doctor|null $editedDoctor
 * @property-read \App\Models\Subcompany|null $editedSubcompany
 * @property-read \App\Models\Patient $patient
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord query()
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereAuditedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereAuditedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereAuditorNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereDoctorVisitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereEditedAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereEditedAgeDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereEditedAgeMonth($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereEditedAgeYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereEditedCompanyRelationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereEditedDoctorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereEditedExpireDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereEditedGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereEditedGuarantor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereEditedInsuranceNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereEditedPatientName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereEditedPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereEditedSubcompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereOriginalPatientDataSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuditedPatientRecord whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class AuditedPatientRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id', 'doctor_visit_id', 'audited_by_user_id', 'audited_at',
        'status', 'auditor_notes', 'original_patient_data_snapshot',
        'edited_patient_name', 'edited_phone', 'edited_gender', 
        'edited_age_year', 'edited_age_month', 'edited_age_day', 'edited_address',
        'edited_doctor_id', 'edited_insurance_no', 'edited_expire_date', 
        'edited_guarantor', 'edited_subcompany_id', 'edited_company_relation_id',
    ];

    protected $casts = [
        'audited_at' => 'datetime',
        'original_patient_data_snapshot' => 'array',
        'edited_expire_date' => 'date',
        // Cast booleans for edited fields if any were booleans
    ];

    public function patient() { return $this->belongsTo(Patient::class); }
    public function doctorVisit() { return $this->belongsTo(DoctorVisit::class); }
    public function auditor() { return $this->belongsTo(User::class, 'audited_by_user_id'); }
    public function editedDoctor() { return $this->belongsTo(Doctor::class, 'edited_doctor_id'); }
    public function editedSubcompany() { return $this->belongsTo(Subcompany::class, 'edited_subcompany_id'); }
    public function editedCompanyRelation() { return $this->belongsTo(CompanyRelation::class, 'edited_company_relation_id'); }

    public function auditedRequestedServices()
    {
        return $this->hasMany(AuditedRequestedService::class);
    }
}
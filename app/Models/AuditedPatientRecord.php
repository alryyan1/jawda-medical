<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon; // For date formatting if needed

class AuditedPatientRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'doctor_visit_id' => $this->doctor_visit_id,
            'audited_by_user_id' => $this->audited_by_user_id,
            'audited_at' => $this->audited_at ? Carbon::parse($this->audited_at)->toIso8601String() : null,
            'status' => $this->status,
            'auditor_notes' => $this->auditor_notes,
            'original_patient_data_snapshot' => $this->original_patient_data_snapshot, // Already JSON

            // Audited/Edited Patient Information
            'edited_patient_name' => $this->edited_patient_name,
            'edited_phone' => $this->edited_phone,
            'edited_gender' => $this->edited_gender,
            'edited_age_year' => $this->edited_age_year,
            'edited_age_month' => $this->edited_age_month,
            'edited_age_day' => $this->edited_age_day,
            'edited_address' => $this->edited_address,
            'edited_doctor_id' => $this->edited_doctor_id,
            'edited_insurance_no' => $this->edited_insurance_no,
            'edited_expire_date' => $this->edited_expire_date ? Carbon::parse($this->edited_expire_date)->toDateString() : null,
            'edited_guarantor' => $this->edited_guarantor,
            'edited_subcompany_id' => $this->edited_subcompany_id,
            'edited_company_relation_id' => $this->edited_company_relation_id,
            
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            // Relationships (conditionally loaded)
            'patient' => new PatientStrippedResource($this->whenLoaded('patient')), // Or full PatientResource
            'doctor_visit' => new DoctorVisitResource($this->whenLoaded('doctorVisit')), // Or full DoctorVisitResource
            'auditor' => new UserStrippedResource($this->whenLoaded('auditor')),
            'edited_doctor' => new DoctorStrippedResource($this->whenLoaded('editedDoctor')),
            'edited_subcompany' => new SubcompanyStrippedResource($this->whenLoaded('editedSubcompany')),
            'edited_company_relation' => new CompanyRelationStrippedResource($this->whenLoaded('editedCompanyRelation')),
            'audited_requested_services' => AuditedRequestedServiceResource::collection($this->whenLoaded('auditedRequestedServices')),
        ];
    }
}
<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'age_year' => $this->age_year,
            'age_month' => $this->age_month,
            'age_day' => $this->age_day,
            'address' => $this->address,
            'company_id' => $this->company_id,
            'company' => new CompanyResource($this->whenLoaded('company')),
            'doctor_id_on_patient_record' => $this->doctor_id, // Clarify this field's purpose
            'assigned_doctor_for_visit' => new DoctorResource($this->whenLoaded('doctorVisits', function() {
                // Get doctor from the latest/relevant visit if needed, or pass selected doctor from form
                return $this->doctorVisits->first()->doctor ?? null; // Example
            })),
            'notes_from_registration' => $this->present_complains, // Example mapping
            // Include other necessary fields
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            // Include initial visit details if helpful
            'initial_visit' => new DoctorVisitResource($this->whenLoaded('doctorVisits', function() {
                 return $this->doctorVisits->sortByDesc('created_at')->first();
            })),
        ];
    }
}
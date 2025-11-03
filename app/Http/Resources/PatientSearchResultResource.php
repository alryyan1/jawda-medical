<?php namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class PatientSearchResultResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'name' => $this?->patient?->name,
            'phone' => $this?->patient?->phone,
            'gender' => $this?->patient?->gender,
            'age_year' => $this?->patient?->age_year, // For quick display
            'last_visit_id' => $this?->id,
            'last_visit_date' => $this?->created_at?->toDateString(),
            'last_visit_doctor_name' => $this?->doctor?->name,
            'last_visit_doctor_id' => $this?->doctor?->id,
            'last_visit_file_id' => $this?->file_id, // For copying
            'last_visit_company_name' => $this?->patient?->company?->name,
            'last_visit_company_id' => $this?->patient?->company?->id,
        ];
    }
}
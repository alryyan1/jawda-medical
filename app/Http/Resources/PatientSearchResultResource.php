<?php namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class PatientSearchResultResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'age_year' => $this->age_year, // For quick display
            'last_visit_id' => $this?->id,
            'last_visit_date' => $this?->visit_date?->toDateString(),
            'last_visit_doctor_name' => $this?->doctor?->name,
            'last_visit_file_id' => $this?->file_id, // For copying
        ];
    }
}
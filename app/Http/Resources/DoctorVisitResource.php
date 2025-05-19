<?php
 namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DoctorVisitResource extends JsonResource {
    public function toArray(Request $request) {
        return [
            'id' => $this->id,
            'visit_date' => $this->visit_date,
            'status' => $this->status,
            'doctor' => new DoctorResource($this->whenLoaded('doctor')),
            'patient_name' => $this->whenLoaded('patient', $this->patient->name), // Avoid full patient resource if not needed
            // ... other visit details
        ];
    }
}
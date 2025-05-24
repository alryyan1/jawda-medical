<?php namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class PatientLabQueueItemResource extends JsonResource {
    public function toArray(Request $request): array {
        // $this->resource is a DoctorVisit model instance with aggregated data
        return [
            'visit_id' => $this->visit_id,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient_name,
            'sample_id' => $this->labRequests->first()->sample_id ?? ($this->labRequests->first()->id ?? $this->visit_id), // Example logic for display ID
            'lab_request_ids' => $this->labRequests->pluck('id')->toArray(),
            'oldest_request_time' => $this->oldest_request_time, // Comes from withMin
            'test_count' => (int) $this->test_count, // Comes from withCount
            // 'status_summary' => ... // Calculate if needed
        ];
    }
}
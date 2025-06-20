<?php namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class PatientLabQueueItemResource extends JsonResource {
    public function toArray(Request $request): array {
        // $this->resource is a DoctorVisit model instance with aggreg
        $allLabRequests = $this->patientLabRequests()->get(); // Get all lab requests for this visit
        $unpaidCount = $allLabRequests->where('is_paid', false)->count();
        return [
            'visit_id' => $this->visit_id,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient_name,
            'lab_number' => $this->patient->visit_number,
            'sample_id' => $this->patientLabRequests->first()->sample_id ?? ($this->patientLabRequests->first()->id ?? $this->visit_id), // Example logic for display ID
            'lab_request_ids' => $this->patientLabRequests->pluck('id')->toArray(),
            'oldest_request_time' => $this->oldest_request_time, // Comes from withMin
            'test_count' => (int) $this->test_count, // Comes from withCount
            'all_requests_paid' => $unpaidCount === 0 && $allLabRequests->isNotEmpty(), // NEW

            // 'status_summary' => ... // Calculate if needed
        ];
    }
}
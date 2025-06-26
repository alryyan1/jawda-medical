<?php namespace App\Http\Resources;

use App\Models\LabRequest;
use App\Models\Patient;
use App\Services\WhatsAppService;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class PatientLabQueueItemResource extends JsonResource {
    public function toArray(Request $request): array {
            // $this->resource is the DoctorVisit model instance or a similar object from the query
            $patientModel = Patient::find($this->patient_id); // Fetch full patient for lock status

            // For all_requests_paid_for_badge, we need to know if all *valid* requests for the visit are paid
            // This can be intensive if done for every item. Consider optimizing if performance is an issue.
            $allValidRequestsForVisit = LabRequest::where('pid', $this->patient_id)
                                                  ->get();
            $unpaidValidRequestsCount = $allValidRequestsForVisit->where('is_paid', false)->count();
            $areAllValidRequestsPaid = $allValidRequestsForVisit->isNotEmpty() && $unpaidValidRequestsCount === 0;
        // $this->resource is a DoctorVisit model instance with aggreg
        $allLabRequests = $this->patientLabRequests()->get(); // Get all lab requests for this visit
        $unpaidCount = $allLabRequests->where('is_paid', false)->count();
        return [
            'visit_id' => $this->visit_id,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient_name,
            'patient_phone_for_whatsapp' => $patientModel ? WhatsAppService::formatPhoneNumberForWaApi($patientModel->phone) : null,
            'is_result_locked' => $patientModel ? (bool) $patientModel->result_is_locked : false,
            'is_printed'=>$this->patient->result_print_date != null,
            'print_date'=>$this->patient->result_print_date,
             'phone' => $this->patient->phone,
            'lab_number' => $this->patient->visit_number,
            'sample_id' => $this->patientLabRequests->first()->sample_id ?? ($this->patientLabRequests->first()->id ?? $this->visit_id), // Example logic for display ID
            'lab_request_ids' => $this->patientLabRequests->pluck('id')->toArray(),
            'oldest_request_time' => $this->oldest_request_time, // Comes from withMin
            'test_count' => (int) $this->test_count, // Comes from withCount
            'all_requests_paid' => $unpaidCount === 0 && $allLabRequests->isNotEmpty(), // NEW
              // The labRequests relation on $this->resource should be the filtered list for sample collection

            // This 'all_requests_paid_for_badge' determines badge color based on overall visit payment status for valid tests
            'all_requests_paid_for_badge' => $areAllValidRequestsPaid,

            // 'status_summary' => ... // Calculate if needed
        ];
    }
}
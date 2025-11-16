<?php
namespace App\Http\Resources;

use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\RequestedResult;
use App\Services\UltramsgService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class PatientLabQueueItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // $this->resource is the DoctorVisit model instance or a similar object from the query
        // $patientModel = Patient::find($this->patient_id); // Fetch full patient for lock status

        // For all_requests_paid_for_badge, we need to know if all *valid* requests for the visit are paid
        // This can be intensive if done for every item. Consider optimizing if performance is an issue.
        $allValidRequestsForVisit = LabRequest::where('pid', $this->patient_id)
            ->get();
        $unpaidValidRequestsCount = $allValidRequestsForVisit->where('is_paid', false)->count();
        $areAllValidRequestsPaid = $allValidRequestsForVisit->isNotEmpty() && $unpaidValidRequestsCount === 0;
        // $this->resource is a DoctorVisit model instance with aggreg
        $allLabRequests = $this->patientLabRequests()->get(); // Get all lab requests for this visit
        $unpaidCount = $allLabRequests->where('is_paid', false)->count();

        // --- Logic to calculate pending results ---
        $totalResultsCount = 0;
        $pendingResultsCount = 0;

        // Assuming '$this->resource' is the DoctorVisit model instance from the query
        $labRequestIds = $this->resource->patientLabRequests->pluck('id');

        if ($labRequestIds->isNotEmpty()) {
            $totalResultsCount = RequestedResult::whereIn('lab_request_id', $labRequestIds)->count();

            $pendingResultsCount = RequestedResult::whereIn('lab_request_id', $labRequestIds)
                ->where(function ($query) {
                    $query->whereNull('result')
                        ->orWhere('result', '=', '');
                })
                ->count();
        }
        $isLastPending = ($totalResultsCount > 0 && $pendingResultsCount === 1);
        $allResultsEntered = ($totalResultsCount > 0 && $pendingResultsCount === 0);
        $isPrinted = $this->patient->result_print_date != null; // Assuming 'is_printed' is on the visit model
        return [
            'total_result_count' => $totalResultsCount,
            'pending_result_count' => $pendingResultsCount,
            'visit_id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient->name,
            'company' => $this->patient->company,
            'shift_id' => $this->patient->shift_id,
            'lab_to_lab_object_id' => $this->patient->lab_to_lab_object_id,
            'has_cbc' => $this->patient->doctorVisit?->hasCbc(),
            'has_chemistry' => $this->patient->doctorVisit?->hasChemistry(),
            'patient_phone_for_whatsapp' => $this->patient ? UltramsgService::formatPhoneNumber($this->patient->phone) : null,
            'is_result_locked' => $this->patient ? (bool) $this->patient->result_is_locked : false,
            'is_printed' => $this->patient->result_print_date != null,
            'print_date' => $this->patient->result_print_date,
            'phone' => $this->patient->phone,
            // Added visit meta and doctor
            'visit_created_at' => $this->created_at,
            'doctor_name' => $this->patient?->doctor->name ?? null,
            'name' => $this->patient->name,
            // Added age representation (years/months/days if available on patient)
            'patient_age' => $this->formatPatientAge($this->patient),
            'lab_number' => $this->patient->visit_number,
            'sample_id' => $this->patientLabRequests->first()->sample_id ?? ($this->patientLabRequests->first()->id ?? $this->visit_id), // Example logic for display ID
            'lab_request_ids' => $this->patientLabRequests->pluck('id')->toArray(),
            'oldest_request_time' => $this->oldest_request_time, // Comes from withMin
            'test_count' => (int) $this->test_count, // Comes from withCount
            'all_requests_paid' => $unpaidCount === 0 && $allLabRequests->isNotEmpty(), // NEW
            // The labRequests relation on $this->resource should be the filtered list for sample collection

            // This 'all_requests_paid_for_badge' determines badge color based on overall visit payment status for valid tests
            'all_requests_paid_for_badge' => $areAllValidRequestsPaid,

            // NEW FIELD
            'is_last_result_pending' => ($totalResultsCount > 0 && $pendingResultsCount === 1),
            // NEW FIELD
            'is_ready_for_print' => ($pendingResultsCount == 0 && !$isPrinted),
            'sample_collected' => $this->patient?->sample_collect_time != null,
            'sample_collection_time' => $this->patient?->sample_collect_time,
            'result_url' => $this->patient?->result_url,
            'registered_by' => $this->patient?->user?->name,
            'auth_date' => $this->patient?->auth_date,
            'result_auth' => $this->patient?->result_auth,
            'id' => $this->id,
            // 'status_summary' => ... // Calculate if needed
        ];
    }

    protected function formatPatientAge($patient): ?string
    {
        if (!$patient)
            return null;
        $y = (int) ($patient->age_year ?? 0);
        $m = (int) ($patient->age_month ?? 0);
        $d = (int) ($patient->age_day ?? 0);
        $parts = [];
        if ($y > 0)
            $parts[] = $y . 'Y';
        if ($m > 0)
            $parts[] = $m . 'M';
        if ($d > 0 || empty($parts))
            $parts[] = $d . 'D';
        return implode(' ', $parts);
    }
}
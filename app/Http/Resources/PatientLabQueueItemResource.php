<?php
namespace App\Http\Resources;

use App\Models\HormoneResult;
use App\Models\Mindray;
use App\Models\SysmexResult;
use App\Services\UltramsgService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientLabQueueItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Use eager-loaded data - NO additional queries!
        $patient = $this->patient;
        // Use labRequests (direct relation) if available, fallback to patientLabRequests
        $labRequests = $this->relationLoaded('labRequests') 
            ? $this->labRequests 
            : ($this->relationLoaded('patientLabRequests') ? $this->patientLabRequests : collect());
        
        // Calculate from eager-loaded results (no DB queries)
        $totalResultsCount = 0;
        $pendingResultsCount = 0;
        $unpaidCount = 0;
        
        foreach ($labRequests as $lr) {
            if (!$lr->is_paid) {
                $unpaidCount++;
            }
            // Use eager-loaded results relation
            if ($lr->relationLoaded('results')) {
                foreach ($lr->results as $result) {
                    $totalResultsCount++;
                    if (empty($result->result)) {
                        $pendingResultsCount++;
                    }
                }
            }
        }
        
        $isPrinted = $patient->result_print_date != null;
        $areAllPaid = $labRequests->isNotEmpty() && $unpaidCount === 0;
        
        // Check for CBC/Chemistry/Hormone by checking actual database tables
        $hasCbc = SysmexResult::where('doctorvisit_id', '=', $this->id)->exists();
        $hasChemistry = Mindray::where('doctorvisit_id', '=', $this->id)->exists();
        $hasHormone = HormoneResult::where('doctorvisit_id', '=', $this->id)->exists();
        
        return [
            'total_result_count' => $totalResultsCount,
            'pending_result_count' => $pendingResultsCount,
            'visit_id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_name' => $patient->name ?? null,
            'company' => $patient->company ?? null, // Eager loaded
            'shift_id' => $patient->shift_id ?? null,
            'lab_to_lab_object_id' => $patient->lab_to_lab_object_id ?? null,
            'has_cbc' => $hasCbc,
            'has_chemistry' => $hasChemistry,
            'has_hormone' => $hasHormone,
            'patient_phone_for_whatsapp' => $patient ? UltramsgService::formatPhoneNumber($patient->phone) : null,
            'is_result_locked' => (bool) ($patient->result_is_locked ?? false),
            'is_printed' => $isPrinted,
            'print_date' => $patient->result_print_date ?? null,
            'phone' => $patient->phone ?? null,
            'visit_created_at' => $this->created_at,
            'doctor_name' => $patient->doctor->name ?? null, // Eager loaded
            'name' => $patient->name ?? null,
            'patient_age' => $this->formatPatientAge($patient),
            'lab_number' => $patient->visit_number ?? null,
            'sample_id' => $labRequests->first()->id ?? $this->visit_id,
            'lab_request_ids' => $labRequests->pluck('id')->toArray(),
            'oldest_request_time' => $this->oldest_request_time ?? null,
            'test_count' => (int) ($this->test_count ?? $labRequests->count()),
            'all_requests_paid' => $areAllPaid,
            'all_requests_paid_for_badge' => $areAllPaid,
            'is_last_result_pending' => ($totalResultsCount > 0 && $pendingResultsCount === 1),
            'is_ready_for_print' => ($pendingResultsCount == 0 && !$isPrinted),
            'sample_collected' => ($patient->sample_collect_time ?? null) != null,
            'sample_collection_time' => $patient->sample_collect_time ?? null,
            'sample_collected_by' => $patient->sampleCollectedBy->name ?? null, // Eager loaded
            'result_url' => $patient->result_url ?? null,
            'registered_by' => $patient->user->name ?? null, // Eager loaded
            'auth_date' => $patient->auth_date ?? null,
            'result_auth' => $patient->result_auth ?? null,
            'id' => $this->id,
        ];
    }

    protected function formatPatientAge($patient): ?string
    {
        if (!$patient) return null;
        $y = (int) ($patient->age_year ?? 0);
        $m = (int) ($patient->age_month ?? 0);
        $d = (int) ($patient->age_day ?? 0);
        $parts = [];
        if ($y > 0) $parts[] = $y . 'Y';
        if ($m > 0) $parts[] = $m . 'M';
        if ($d > 0 || empty($parts)) $parts[] = $d . 'D';
        return implode(' ', $parts);
    }
}
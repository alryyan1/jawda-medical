<?php

namespace App\Http\Resources;

use App\Models\HormoneResult;
use App\Models\Mindray;
use App\Models\SysmexResult;
use App\Services\WhatsAppCloudApiService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection as SupportCollection;

class PatientLabQueueItemResource extends JsonResource
{
    /**
     * Per-request cache of visit ids that have a CBC/chemistry/hormone record,
     * keyed by visit id. Populated once per collection() call to avoid running
     * three exists() queries for every row (these tables have no index on
     * doctorvisit_id, so per-row queries do a full table scan each).
     */
    protected static array $cbcVisitIds = [];

    protected static array $chemistryVisitIds = [];

    protected static array $hormoneVisitIds = [];

    /**
     * @param  \Illuminate\Support\Collection|array<int, mixed>|\Illuminate\Database\Eloquent\Collection  $resource
     */
    public static function collection($resource): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        static::preloadDiagnosticFlags(SupportCollection::wrap($resource)->pluck('id')->filter()->unique()->values());

        return parent::collection($resource);
    }

    /**
     * Batch-fetch which of the given visit ids have CBC/chemistry/hormone
     * records, replacing N per-row exists() queries with 3 whereIn() queries.
     */
    protected static function preloadDiagnosticFlags(SupportCollection $visitIds): void
    {
        if ($visitIds->isEmpty()) {
            static::$cbcVisitIds = [];
            static::$chemistryVisitIds = [];
            static::$hormoneVisitIds = [];

            return;
        }

        static::$cbcVisitIds = SysmexResult::query()
            ->whereIn('doctorvisit_id', $visitIds)
            ->distinct()
            ->pluck('doctorvisit_id')
            ->flip()
            ->all();

        static::$chemistryVisitIds = Mindray::query()
            ->whereIn('doctorvisit_id', $visitIds)
            ->distinct()
            ->pluck('doctorvisit_id')
            ->flip()
            ->all();

        static::$hormoneVisitIds = HormoneResult::query()
            ->whereIn('doctorvisit_id', $visitIds)
            ->distinct()
            ->pluck('doctorvisit_id')
            ->flip()
            ->all();
    }

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
            if (! $lr->is_paid) {
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

        // Check for CBC/Chemistry/Hormone, preferring the batch-preloaded flags
        // (see preloadDiagnosticFlags()) and falling back to a single-row
        // query when the resource is used standalone via `new static(...)`.
        $hasCbc = array_key_exists($this->id, static::$cbcVisitIds)
            ? true
            : SysmexResult::where('doctorvisit_id', '=', $this->id)->exists();
        $hasChemistry = array_key_exists($this->id, static::$chemistryVisitIds)
            ? true
            : Mindray::where('doctorvisit_id', '=', $this->id)->exists();
        $hasHormone = array_key_exists($this->id, static::$hormoneVisitIds)
            ? true
            : HormoneResult::where('doctorvisit_id', '=', $this->id)->exists();

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
            'patient_phone_for_whatsapp' => $patient ? WhatsAppCloudApiService::formatPhoneNumber($patient->phone) : null,
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
            'is_ready_for_print' => ($pendingResultsCount == 0 && ! $isPrinted),
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
        if (! $patient) {
            return null;
        }
        $y = (int) ($patient->age_year ?? 0);
        $m = (int) ($patient->age_month ?? 0);
        $d = (int) ($patient->age_day ?? 0);
        $parts = [];
        if ($y > 0) {
            $parts[] = $y.'Y';
        }
        if ($m > 0) {
            $parts[] = $m.'M';
        }
        if ($d > 0 || empty($parts)) {
            $parts[] = $d.'D';
        }

        return implode(' ', $parts);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditedPatientRecord;
use App\Models\AuditedRequestedService;
use App\Models\Company;
use App\Models\DoctorVisit;
use App\Models\Patient;
use App\Models\RequestedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\AuditedPatientRecordResource; // Create this
use App\Http\Resources\AuditedRequestedServiceResource; // Create this
use App\Http\Resources\DoctorVisitResource; // Or a simpler one for the list
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class InsuranceAuditController extends Controller
{
    public function __construct()
    {
        // Add permissions: e.g., 'audit insurance_claims', 'edit audited_records'
    }

    /**
     * List patient visits eligible for auditing based on filters.
     */
    public function listAuditableVisits(Request $request)
    {
        // $this->authorize('viewAny', AuditedPatientRecord::class); // Example policy

        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'company_id' => 'required|integer|exists:companies,id', // Company is required for this page
            'patient_name' => 'nullable|string|max:255',
            'audit_status' => 'nullable|string|in:all,pending_review,verified,needs_correction,rejected', // Filter by audit status
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);

        $query = DoctorVisit::query()
            ->with([
                'patient.company', // For company name display
                'doctor:id,name',
                'auditRecord:id,status,audited_at,audited_by_user_id', // Load existing audit record if any
                'auditRecord.auditor:id,name'
            ])
            ->whereHas('patient', function ($q) use ($request) {
                $q->whereNotNull('company_id'); // Must be insurance patient
                if ($request->filled('company_id')) {
                    $q->where('company_id', $request->company_id);
                }
                if ($request->filled('patient_name')) {
                    $q->where('name', 'LIKE', '%' . $request->patient_name . '%');
                }
            });

        if ($request->filled('date_from')) {
            $query->whereDate('visit_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('visit_date', '<=', $request->date_to);
        }

        if ($request->filled('audit_status') && $request->audit_status !== 'all') {
            $status = $request->audit_status;
            if ($status === 'pending_review') { // Special case for visits not yet audited or explicitly pending
                $query->where(function($q) {
                    $q->doesntHave('auditRecord')
                      ->orWhereHas('auditRecord', fn($aq) => $aq->where('status', 'pending_review'));
                });
            } else {
                $query->whereHas('auditRecord', fn($aq) => $aq->where('status', $status));
            }
        }
        
        $visits = $query->latest('visit_date')->latest('id')
                        ->paginate($request->get('per_page', 15));
        
        // You might use a more tailored resource if DoctorVisitResource is too heavy
        return DoctorVisitResource::collection($visits); 
    }

    /**
     * Get or create an audit record for a specific DoctorVisit.
     */
    public function getOrCreateAuditRecordForVisit(DoctorVisit $doctorVisit)
    {
        // $this->authorize('audit', $doctorVisit);

        $auditRecord = AuditedPatientRecord::firstOrCreate(
            ['doctor_visit_id' => $doctorVisit->id],
            [
                'patient_id' => $doctorVisit->patient_id,
                'audited_by_user_id' => Auth::id(), // Or null until first save
                'status' => 'pending_review',
                // Snapshot key original data if desired
                'original_patient_data_snapshot' => $doctorVisit->patient->only(['name', 'phone', 'gender', 'age_year', 'age_month', 'age_day', 'address', 'insurance_no', 'expire_date', 'guarantor', 'subcompany_id', 'company_relation_id']),
                'edited_patient_name' => $doctorVisit->patient->name,
                'edited_phone' =>  $doctorVisit->patient->phone,
                // ... prefill other edited fields from original patient
            ]
        );

        $auditRecord->load([
            'patient.company', 'doctorVisit.doctor', 'auditor', 
            'editedDoctor', 'editedSubcompany', 'editedCompanyRelation',
            'auditedRequestedServices.service', 
            'auditedRequestedServices.originalRequestedService'
        ]);
        return new AuditedPatientRecordResource($auditRecord);
    }
    
    /**
     * Update the patient demographic/insurance information on the audit record.
     */
    public function updateAuditedPatientInfo(Request $request, AuditedPatientRecord $auditedPatientRecord)
    {
        // $this->authorize('update', $auditedPatientRecord);

        $validated = $request->validate([
            'edited_patient_name' => 'sometimes|required|string|max:255',
            'edited_phone' => 'nullable|string|max:20',
            'edited_gender' => ['sometimes','required', Rule::in(['male', 'female', 'other'])],
            'edited_age_year' => 'nullable|integer|min:0',
            'edited_age_month' => 'nullable|integer|min:0|max:11',
            'edited_age_day' => 'nullable|integer|min:0|max:30',
            'edited_address' => 'nullable|string|max:1000',
            'edited_doctor_id' => 'nullable|integer|exists:doctors,id', // Changed doctor for this audited claim
            'edited_insurance_no' => 'nullable|string|max:255',
            'edited_expire_date' => 'nullable|date_format:Y-m-d',
            'edited_guarantor' => 'nullable|string|max:255',
            'edited_subcompany_id' => 'nullable|integer|exists:subcompanies,id',
            'edited_company_relation_id' => 'nullable|integer|exists:company_relations,id',
            'auditor_notes' => 'nullable|string',
        ]);
        // Ensure company_id is not changed
        // $validated['company_id'] = $auditedPatientRecord->patient->company_id;

        $auditedPatientRecord->update($validated);
        return new AuditedPatientRecordResource($auditedPatientRecord->fresh()->load(['patient.company', /* other relations */]));
    }

    /**
     * Copy original requested services to the audited_requested_services table for this audit record.
     * This should typically only be done once per audit record, or if explicitly requested to reset.
     */
    public function copyServicesToAudit(AuditedPatientRecord $auditedPatientRecord)
    {
        // $this->authorize('update', $auditedPatientRecord);

        if ($auditedPatientRecord->auditedRequestedServices()->exists()) {
            return response()->json(['message' => 'الخدمات المدققة موجودة بالفعل لهذا السجل. لحذفها والبدء من جديد، استخدم إجراء آخر.'], 409);
        }

        $originalServices = RequestedService::where('doctorvisits_id', $auditedPatientRecord->doctor_visit_id)->get();
        if ($originalServices->isEmpty()) {
            return response()->json(['message' => 'لا توجد خدمات أصلية لنسخها لهذه الزيارة.', 'copied_count' => 0], 404);
        }

        $auditedServicesData = [];
        foreach ($originalServices as $rs) {
            // Calculate initial audited endurance based on contract or original endurance
            // This is where complex pricing/contract rules for auditing would apply.
            // For now, we copy the original values as a starting point.
            $auditedServicesData[] = [
                'audited_patient_record_id' => $auditedPatientRecord->id,
                'original_requested_service_id' => $rs->id,
                'service_id' => $rs->service_id,
                'audited_price' => $rs->price,
                'audited_count' => $rs->count,
                'audited_discount_per' => $rs->discount_per,
                'audited_discount_fixed' => $rs->discount,
                'audited_endurance' => $rs->endurance, // This is key for insurance
                'audited_status' => 'pending_review', // Initial status
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        AuditedRequestedService::insert($auditedServicesData);
        $auditedPatientRecord->load('auditedRequestedServices.service'); // Reload

        return response()->json([
            'message' => 'تم نسخ الخدمات للتدقيق بنجاح.',
            'copied_count' => count($auditedServicesData),
            'data' => AuditedRequestedServiceResource::collection($auditedPatientRecord->auditedRequestedServices)
        ]);
    }

    /**
     * Store a new audited service (if auditor adds one not in original).
     */
    public function storeAuditedService(Request $request)
    {
        // $this->authorize('create', AuditedRequestedService::class);
        $validated = $request->validate([
            'audited_patient_record_id' => 'required|integer|exists:audited_patient_records,id',
            'service_id' => 'required|integer|exists:services,id',
            // ... other fields from AuditedRequestedService fillable ...
            'audited_price' => 'required|numeric|min:0',
            'audited_count' => 'required|integer|min:1',
            'audited_discount_per' => 'nullable|numeric|min:0|max:100',
            'audited_discount_fixed' => 'nullable|numeric|min:0',
            'audited_endurance' => 'required|numeric|min:0',
            'audited_status' => ['required', Rule::in(['approved_for_claim', 'rejected_by_auditor', 'pending_edits'])],
            'auditor_notes_for_service' => 'nullable|string',
        ]);
        $auditedService = AuditedRequestedService::create($validated);
        return new AuditedRequestedServiceResource($auditedService->load('service'));
    }
    
    /**
     * Update an audited service line item.
     */
    public function updateAuditedService(Request $request, AuditedRequestedService $auditedRequestedService)
    {
        // $this->authorize('update', $auditedRequestedService);
         $validated = $request->validate([
            // Only allow updating fields relevant to audit adjustment
            'audited_price' => 'sometimes|required|numeric|min:0',
            'audited_count' => 'sometimes|required|integer|min:1',
            'audited_discount_per' => 'nullable|numeric|min:0|max:100',
            'audited_discount_fixed' => 'nullable|numeric|min:0',
            'audited_endurance' => 'sometimes|required|numeric|min:0',
            'audited_status' => ['sometimes','required', Rule::in(['approved_for_claim', 'rejected_by_auditor', 'pending_edits', 'pending_review'])],
            'auditor_notes_for_service' => 'nullable|string',
        ]);
        $auditedRequestedService->update($validated);
        return new AuditedRequestedServiceResource($auditedRequestedService->load('service'));
    }

    /**
     * Delete an audited service line item (auditor decided it shouldn't be claimed).
     */
    public function deleteAuditedService(AuditedRequestedService $auditedRequestedService)
    {
        // $this->authorize('delete', $auditedRequestedService);
        $auditedRequestedService->delete();
        return response()->json(null, 204);
    }

    /**
     * Mark an audit record as verified.
     */
    public function verifyAuditRecord(Request $request, AuditedPatientRecord $auditedPatientRecord)
    {
        // $this->authorize('verify', $auditedPatientRecord);
        // Potentially validate auditor_notes if status is 'rejected' or 'needs_correction'
        $request->validate([
            'status' => ['required', Rule::in(['verified', 'needs_correction', 'rejected'])],
            'auditor_notes' => 'nullable|string|max:2000',
        ]);

        $auditedPatientRecord->update([
            'status' => $request->status,
            'audited_at' => now(),
            'audited_by_user_id' => Auth::id(),
            'auditor_notes' => $request->input('auditor_notes', $auditedPatientRecord->auditor_notes)
        ]);
        return new AuditedPatientRecordResource($auditedPatientRecord->fresh()->load(['auditor']));
    }

    public function exportPdf(Request $request) {/* ... PDF generation logic ... */}
    public function exportExcel(Request $request) {/* ... Excel generation logic ... */}

}
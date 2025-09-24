<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorVisit;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\Shift;
use Illuminate\Http\Request;
use App\Http\Resources\DoctorVisitResource;
use App\Models\DoctorShift;
use App\Models\File;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DoctorVisitController extends Controller
{
    public function __construct()
    {
        // Define permissions like 'list visits', 'create visits', 'edit visit_status', etc.
        // $this->middleware('can:list doctor_visits')->only('index');
        // $this->middleware('can:view doctor_visits')->only('show');
        // $this->middleware('can:create doctor_visits')->only('store');
        // $this->middleware('can:update doctor_visits')->only('update'); // For general updates
        // $this->middleware('can:update visit_status')->only('updateStatus');
    }

    /**
     * Display a listing of doctor visits.
     * Useful for an admin overview page, with filters.
     */
    // public function index(Request $request)
    // {
    //     $query = DoctorVisit::with(['patient:id,name,phone', 'doctor:id,name', 'createdByUser:id,name'])
    //                         ->latest(); // Default order

    //     if ($request->filled('patient_id')) {
    //         $query->where('patient_id', $request->patient_id);
    //     }
    //     if ($request->filled('doctor_id')) {
    //         $query->where('doctor_id', $request->doctor_id);
    //     }
    //     if ($request->filled('status')) {
    //         $query->where('status', $request->status);
    //     }
    //     if ($request->filled('date_from')) {
    //         $query->whereDate('visit_date', '>=', $request->date_from);
    //     }
    //     if ($request->filled('date_to')) {
    //         $query->whereDate('visit_date', '<=', $request->date_to);
    //     }
    //     // Add more filters as needed (shift_id, visit_type, etc.)

    //     $visits = $query->paginate($request->get('per_page', 15));
    //     return DoctorVisitResource::collection($visits);
    // }
    public function index(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'status' => 'nullable|string', // Add validation for allowed statuses if needed
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = DoctorVisit::with([
            'patient:id,name,phone,gender,age_year,age_month,age_day,company_id',
            'patient.company:id,name', // Eager load company for patient
            'doctor:id,name',          // EAGER LOAD DOCTOR
            'createdByUser:id,name',
            'requestedServices.service', // For calculating totals
            'patientLabRequests.mainTest' ,
            'patient.user:id,username'        // For calculating totals
        ])
        ->latest('created_at'); // Or created_at if visit_time is not reliable

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->date_from)->startOfDay(),
                Carbon::parse($request->date_to)->endOfDay()
            ]);
        } elseif ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        } elseif ($request->filled('date_to')) {
             $query->whereDate('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }
         else {
            // Default to today if no date range is specified
            $query->whereDate('created_at', Carbon::today());
        }

        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->whereHas('patient', function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('phone', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('id', $searchTerm);
            });
        }

        $visits = $query->paginate($request->get('per_page', 15));

        // The DoctorVisitResource will need to calculate/include total_discount
        return DoctorVisitResource::collection($visits);
    }
    
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'doctor_id' => 'required|exists:doctors,id',
            'doctor_shift_id' => 'nullable|exists:doctor_shifts,id',
            // 'appointment_id' => 'nullable|exists:appointments,id|unique:doctor_visits,appointment_id',
            'file_id' => 'nullable|exists:files,id',
            'visit_date' => 'required|date_format:Y-m-d',
            'visit_time' => 'nullable|date_format:H:i:s',
            'status' => ['nullable', 'string', Rule::in(['waiting', 'with_doctor', 'lab_pending', 'imaging_pending', 'payment_pending', 'completed', 'cancelled', 'no_show'])],
            'visit_type' => 'nullable|string|max:100',
            'queue_number' => 'nullable|integer',
            'reason_for_visit' => 'nullable|string|max:1000',
            'visit_notes' => 'nullable|string',
            'is_new' => 'sometimes|boolean',
            'number' => 'sometimes|integer', // Original 'number' column
            'only_lab' => 'sometimes|boolean',
        ]);

        // Determine current open shift if not provided and required
        // $currentClinicShift = Shift::open()->latest()->first();
        // if (!$currentClinicShift && empty($validatedData['shift_id'])) {
        //     return response()->json(['message' => 'No open clinic shift available.'], 400);
        // }

        // Calculate the visit number before creating the visit
        $visitNumber = $validatedData['number'] ?? (
            isset($validatedData['patient_id'])
            ? (DoctorVisit::where('patient_id', $validatedData['patient_id'])->count() + 1)
            : 1
        );

        if(isset($validatedData['shift_id'])){
            $shift_id = $validatedData['shift_id'];
        }else{
            $shift_id = Shift::open()->latest()->first()->id;
        }
        $visit = DoctorVisit::create([
            'patient_id' => $validatedData['patient_id'],
            'doctor_id' => $validatedData['doctor_id'],
            'user_id' => Auth::id(),
            'shift_id' => $shift_id, 
            'doctor_shift_id' => $validatedData['doctor_shift_id'] ?? null,
            // 'appointment_id' => $validatedData['appointment_id'] ?? null,
            'file_id' => $validatedData['file_id'] ?? null,
            'visit_date' => $validatedData['visit_date'] ?? Carbon::today(),
            'visit_time' => $validatedData['visit_time'] ?? Carbon::now()->format('H:i:s'),
            'status' => $validatedData['status'] ?? 'waiting',
            'visit_type' => $validatedData['visit_type'] ?? 'New',
            'queue_number' => $validatedData['queue_number'] ?? null, // Implement queue logic if needed
            'reason_for_visit' => $validatedData['reason_for_visit'] ?? null,
            'visit_notes' => $validatedData['visit_notes'] ?? null,
            'is_new' => $validatedData['is_new'] ?? true,
            'number' => $visitNumber, // Example for 'number'
            'only_lab' => $validatedData['only_lab'] ?? false,
        ]);

        return new DoctorVisitResource($visit->load(['patient.subcompany', 'patient.doctor']));
    }

    /**
     * Display the specified doctor visit.
     */
    public function show(DoctorVisit $doctorVisit) // Route model binding
    {
        // Load all relevant data for displaying a single visit's details
        // $doctorVisit->load(['patient', 'doctor', 'createdByUser', 'generalShift', 'doctorShift', 'requestedServices.service.serviceGroup', 'doctorShift.doctor', 'patientLabRequests']);
        $doctorVisit->load(['patient.subcompany', 'patient.doctor','patientLabRequests'
        , 'patientLabRequests','doctor','patientLabRequests.mainTest','createdByUser']);
        return new DoctorVisitResource($doctorVisit->load(['patient.subcompany', 'patient.doctor']));
    }

    /**
     * Update the specified doctor visit in storage.
     * (e.g., updating notes, status, clinical details related to the visit)
     */
    public function update(Request $request, DoctorVisit $doctorVisit)
    {
        $validatedData = $request->validate([
            // Allow updating specific fields of a visit
            'doctor_id' => 'sometimes|required|exists:doctors,id',
            'status' => ['sometimes', 'required', 'string', Rule::in(['waiting', 'with_doctor', 'lab_pending', 'imaging_pending', 'payment_pending', 'completed', 'cancelled', 'no_show'])],
            'visit_type' => 'nullable|string|max:100',
            'reason_for_visit' => 'nullable|string|max:1000',
            'visit_notes' => 'nullable|string',
            // Add other updatable fields like vitals if stored here, or clinical examination findings
        ]);

        $doctorVisit->update($validatedData);
        return new DoctorVisitResource($doctorVisit->load(['patient.subcompany', 'patient.doctor']));
    }

    /**
     * Update only the status of a doctor visit.
     * Useful for quick status changes from the clinic workspace.
     */
    public function updateStatus(Request $request, DoctorVisit $doctorVisit)
    {
        $validatedData = $request->validate([
            'status' => ['required', 'string', Rule::in(['waiting', 'with_doctor', 'lab_pending', 'imaging_pending', 'payment_pending', 'completed', 'cancelled', 'no_show'])],
        ]);

        $doctorVisit->update(['status' => $validatedData['status']]);

        // TODO: Trigger events if needed (e.g., VisitStatusUpdated for WebSockets)
        // event(new VisitStatusUpdated($doctorVisit));

        return new DoctorVisitResource($doctorVisit->load(['patient.subcompany', 'patient.doctor']));
    }


    /**
     * Remove the specified doctor visit from storage.
     * Use with caution. Usually, visits are 'cancelled' rather than deleted.
     */
    public function destroy(DoctorVisit $doctorVisit)
    {
        // Add checks: e.g., cannot delete if services are requested or payments made.
        if ($doctorVisit->requestedServices()->exists()) { // || $doctorVisit->payments()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف الزيارة لارتباطها بخدمات مطلوبة أو مدفوعات.'], 403);
        }
        $doctorVisit->delete();
        return response()->json(null, 204);
    }
    
    /**
     * Reassign a doctor visit to a different doctor's shift.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\DoctorVisit  $doctorVisit
     * @return \App\Http\Resources\DoctorVisitResource|\Illuminate\Http\JsonResponse
     */
    public function reassignToShift(Request $request, DoctorVisit $doctorVisit)
    {
        // Authorization check: Ensure user can perform this action
        // Example: $this->authorize('reassign', $doctorVisit); 
        // Or a more general 'manage clinic_workspace' permission
        // if (!Auth::user()->can('manage_visit_assignments')) { // Example permission
        //     return response()->json(['message' => 'Unauthorized to reassign visits.'], 403);
        // }

        $validated = $request->validate([
            'target_doctor_shift_id' => 'required|integer|exists:doctor_shifts,id',
        ]);

        $targetDoctorShift = DoctorShift::with('doctor')->find($validated['target_doctor_shift_id']);

        if (!$targetDoctorShift) {
            // Should be caught by 'exists' rule, but good to double check
            return response()->json(['message' => ' المناوبة المستهدفة للطبيب غير موجودة.'], 404);
        }

        if ($doctorVisit->doctor_shift_id == $targetDoctorShift->id) {
            return response()->json(['message' => 'الزيارة موجودة بالفعل في هذه المناوبة المحددة.'], 409); // Conflict
        }

        if (!$targetDoctorShift->status) { // Target shift is not active/open
             return response()->json(['message' => 'لا يمكن نقل الزيارة إلى مناوبة طبيب مغلقة.'], 400);
        }
        
        // Business Rule Example: Only allow reassignment if the target doctor shift's general shift is the same as the visit's current general shift
        // OR if the target doctor shift's general shift is currently open.
        $currentGeneralShiftOfVisit = $doctorVisit->generalShift;
        $targetGeneralShiftOfDoctorShift = $targetDoctorShift->generalShift;

        if (!$targetGeneralShiftOfDoctorShift || $targetGeneralShiftOfDoctorShift->is_closed) {
            return response()->json(['message' => 'الوردية العامة للمناوبة المستهدفة مغلقة.'], 400);
        }
        
        // Optional: Check if the target doctor is different and if user has permission to assign to any doctor
        // if ($doctorVisit->doctor_id !== $targetDoctorShift->doctor_id && !Auth::user()->can('reassign_visit_to_any_doctor')) {
        //     return response()->json(['message' => 'غير مصرح لك بنقل الزيارة لطبيب آخر.'], 403);
        // }

        DB::beginTransaction();
        try {
            // Update the visit
            $doctorVisit->doctor_shift_id = $targetDoctorShift->id;
            $doctorVisit->doctor_id = $targetDoctorShift->doctor_id; // Assign to the doctor of the new shift
            
            // Recalculate queue number for the new shift
            // Note: This simple count might lead to race conditions in a high-traffic system.
            // More robust queue numbering might involve a dedicated sequence or atomic operations.
            $newQueueNumber = DoctorVisit::where('doctor_shift_id', $targetDoctorShift->id)
                                        ->count() + 1;
            $doctorVisit->queue_number = $newQueueNumber;
            $doctorVisit->number = $newQueueNumber; // Assuming 'number' is also queue number

            // Optionally, reset status to 'waiting' for the new shift, or keep current status
            // If keeping current status, ensure it's valid for a new shift (e.g., not 'completed')
            if (!in_array($doctorVisit->status, ['completed', 'cancelled', 'no_show'])) {
                $doctorVisit->status = 'waiting';
            }
            // Or, if you always want to reset:
            // $doctorVisit->status = 'waiting';


            $doctorVisit->save();

            DB::commit();

            // Trigger event if needed, e.g., VisitReassigned
            // event(new VisitReassigned($doctorVisit, $originalDoctorShiftId));

            return new DoctorVisitResource($doctorVisit->fresh()->load(['patient.subcompany', 'patient.doctor', 'doctorShift.doctor']));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to reassign visit ID {$doctorVisit->id} to doctor shift ID {$targetDoctorShift->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل نقل الزيارة.', 'error' => 'خطأ داخلي.'], 500);
        }
    }
      /**
     * Create a new patient record (snapshot) and a new DoctorVisit 
     * assigned to a specified target DoctorShift, based on an existing patient.
     * This is typically used when a patient from one active visit needs to be
     * immediately seen by another doctor in a different active shift.
     */
    public function  createCopiedVisitForNewShift(Request $request, Patient $sourcePatient) // $sourcePatient is the patient from the current visit
    {
        // Authorization: User needs permission to create patients and visits,
        // and potentially to assign visits to the target doctor/shift.
        // $this->authorize('create', Patient::class);
        // $this->authorize('create', DoctorVisit::class);

        $validatedData = $request->validate([
            'target_doctor_shift_id' => 'required|integer|exists:doctor_shifts,id',
            'reason_for_visit' => 'nullable|string|max:1000', // Optional reason for the new visit
            // 'copy_all_original_services' => 'nullable|boolean', // Future: Option to also copy original requested services
        ]);

        $targetDoctorShift = DoctorShift::with('doctor')->find($validatedData['target_doctor_shift_id']);

        if (!$targetDoctorShift || !$targetDoctorShift->status) {
            return response()->json(['message' => 'المناوبة المستهدفة للطبيب غير موجودة أو مغلقة.'], 400);
        }

        $currentGeneralShift = Shift::open()->latest('created_at')->first();
        if (!$currentGeneralShift) {
            return response()->json(['message' => 'لا توجد وردية عيادة عامة مفتوحة حالياً.'], 400);
        }
        
        // Ensure the target doctor is not the same as the original visit's doctor if it's the same shift
        // Or handle cases where patient is simply being "moved" to a new queue spot under same doctor but different shift record
        // For this "copy and create new" flow, it's often for a *different* doctor.
        // $originalVisit = DoctorVisit::find($request->input('original_visit_id')); // If you need context from original visit
        // if ($originalVisit && $originalVisit->doctor_id === $targetDoctorShift->doctor_id && $originalVisit->doctor_shift_id === $targetDoctorShift->id) {
        //     return response()->json(['message' => 'لا يمكن نسخ الزيارة لنفس الطبيب في نفس المناوبة.'], 409);
        // }


        DB::beginTransaction();
        try {
            $doctorvist = DoctorVisit::whereHas('patient', function ($query) use ($sourcePatient) {
                $query->where('id', $sourcePatient->id);
            })->latest('created_at')->first();
            if (!$doctorvist) {
                return response()->json(['message' => 'لا يوجد زيارة للمريض.'], 400);
            }
            $fileToUseId = $doctorvist->file_id;

            // 2. Create a new Patient record (snapshot) by replicating the source patient
            $newPatientData = $sourcePatient->replicate()->fill([
                'user_id' => Auth::id(), // User performing this action
                'shift_id' => $currentGeneralShift->id, // Current general shift
                'created_at' => now(),
                'updated_at' => now(),
                'doctor_id' => $targetDoctorShift->doctor_id, // Link to the new doctor
                'visit_number' => DoctorVisit::where('shift_id', $currentGeneralShift->id)->count() + 1, // Visit number within general shift
                'result_auth' => false, // Reset audit/result related flags
                 // Reset any visit-specific flags that might have been on the sourcePatient snapshot
                'is_lab_paid' => false, 'lab_paid' => 0,
                'result_is_locked' => false, 'sample_collected' => false,
                'doctor_finish' => false,
                'doctor_lab_request_confirm' => false,
                'doctor_lab_urgent_confirm' => false,
                'auth_date' => null,
            ])->toArray();
            unset($newPatientData['id']); // Ensure a new patient record is created

            $newPatientSnapshot = Patient::create($newPatientData);

            // 3. Create the new DoctorVisit for this new patient snapshot and target shift
            $newQueueNumber = DoctorVisit::where('doctor_shift_id', $targetDoctorShift->id)
                                        ->count() + 1;

            $newDoctorVisit = $newPatientSnapshot->doctorVisit()->create([
                'doctor_id' => $targetDoctorShift->doctor_id,
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'doctor_shift_id' => $targetDoctorShift->id,
                'file_id' => $fileToUseId,
                'visit_date' => Carbon::today(),
                'visit_time' => Carbon::now()->format('H:i:s'),
                'status' => 'waiting', // New visit starts as waiting for the new doctor
                'reason_for_visit' => $validatedData['reason_for_visit'] ?? 'تحويل من طبيب آخر / زيارة جديدة',
                'is_new' => true, // Considered a "new" encounter for this doctor/shift context
                'number' => $newQueueNumber, 
                'queue_number' => $newQueueNumber,
                'only_lab' => false, // Assuming it's not just for lab by default
            ]);
            
            // TODO (Future): Option to copy original_requested_services to the new visit's requested_services
            // if ($request->input('copy_all_original_services') && $originalVisit) {
            //    foreach($originalVisit->requestedServices as $orig_rs) {
            //        $newDoctorVisit->requestedServices()->create([...data from orig_rs...]);
            //    }
            // }

            DB::commit();
            
            // Return the new DoctorVisit, eager loading what the frontend might need
            return new DoctorVisitResource($newDoctorVisit->load(['patient.subcompany', 'patient.doctor', 'file']));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create copied visit for patient {$sourcePatient->id} to doctor shift {$targetDoctorShift->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل إنشاء زيارة جديدة للمريض.', 'error' => $e->getMessage()], 500);
        }
    }
}

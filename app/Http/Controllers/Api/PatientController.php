<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\DoctorVisit;
use App\Models\Shift; // To get current shift for visit
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Http\Resources\DoctorVisitResource;
use App\Http\Resources\PatientResource;
use App\Http\Resources\PatientSearchResultResource;
use App\Http\Resources\PatientStrippedResource;
use App\Http\Resources\RecentDoctorVisitSearchResource;
// use App\Http\Resources\PatientCollection; // If you have custom pagination
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\DoctorShift;
use App\Models\File;

class PatientController extends Controller
{
    public function __construct()
    {
        // $this->middleware('can:list patients')->only('index');
        // $this->middleware('can:view patients')->only('show');
        // $this->middleware('can:create patients')->only('store');
        // $this->middleware('can:edit patients')->only('update');
        // $this->middleware('can:delete patients')->only('destroy');
    }

    /**
     * Display a listing of the patients.
     */
    public function index(Request $request)
    {
        $query = Patient::with(['company', 'primaryDoctor:id,name', 'doctor']); // Eager load common relations

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('phone', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('id', $searchTerm); // Or patient file number if you have one
            });
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        // Add other filters as needed (e.g., gender)

        $patients = $query->latest()->paginate($request->get('per_page', 15));

        // return new PatientCollection($patients);
        return PatientResource::collection($patients);
    }

    /**
     * Store a newly created patient in storage.
     * This also creates an initial DoctorVisit for the clinic workflow.
     */
    /**
     * Store a NEW patient record and an initial DoctorVisit.
     * A new File record is created and linked to the DoctorVisit.
     */
    public function store(StorePatientRequest $request)
    {
        $validatedPatientData = $request->validated(); // Use validated() directly

        $visitDoctorId = $validatedPatientData['doctor_id'];
        $visitReason = $validatedPatientData['notes'] ?? ($validatedPatientData['present_complains'] ?? 'New Visit');

        // Remove fields that are not part of the Patient model directly or handled separately
        $patientSpecificData = collect($validatedPatientData)->except(['doctor_id', 'notes', 'active_doctor_shift_id'])->toArray();


        $currentGeneralShift = Shift::open()->latest('created_at')->first();
        if (!$currentGeneralShift) {
            return response()->json(['message' => 'لا توجد وردية عيادة مفتوحة حالياً لبدء زيارة.'], 400);
        }
        $activeDoctorShiftId = $request->input('doctor_shift_id');

        if ($request->filled('company_id')) {
            $this->authorize('register insurance_patient');
        } else {
            $this->authorize('register cash_patient');
        }
        DB::beginTransaction();
        try {
            // Check for existing patient with same phone number or identical name
            $existingPatient = null;
            $fileToUseId = null;

            if (!empty($patientSpecificData['phone']) || !empty($patientSpecificData['name'])) {
                $existingPatient = Patient::where(function ($query) use ($patientSpecificData) {
                    if (!empty($patientSpecificData['phone'])) {
                        $query->where('phone', $patientSpecificData['phone']);
                    }
                    if (!empty($patientSpecificData['name'])) {
                        $query->orWhere('name', $patientSpecificData['name']);
                    }
                })->latest()->first();

                if ($existingPatient) {
                    $latestVisit = $existingPatient->doctorVisit()->latest()->first();
                    if ($latestVisit && $latestVisit->file_id) {
                        $fileToUseId = $latestVisit->file_id;
                    }
                }
            }

            // Create a new File record only if we didn't find an existing one to reuse
            if (!$fileToUseId) {
                $file = File::create();
                $fileToUseId = $file->id;
            }

            //the visit number is the number of the in the general shift
            $visitLabNumber = DoctorVisit::where('shift_id', $currentGeneralShift->id)->count() + 1;
            $queueNumber = DoctorVisit::where('doctor_shift_id', $activeDoctorShiftId)->count() + 1;
            // 2. Create the new Patient record for this encounter
            $patient = Patient::create(array_merge($patientSpecificData, [
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'visit_number' => $visitLabNumber,
                'doctor_id' => $visitDoctorId,
                'result_auth' => false,
                'referred' => 'no',
                'discount_comment' => '',
            ]));

            // 3. Create the DoctorVisit record linked to this new Patient record and new File
            $doctorVisit = $patient->doctorVisit()->create([
                'doctor_id' => $visitDoctorId,
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'doctor_shift_id' => $activeDoctorShiftId,
                'file_id' => $fileToUseId,
                'visit_date' => Carbon::today(),
                'visit_time' => Carbon::now()->format('H:i:s'),
                'status' => 'waiting',
                'reason_for_visit' => $visitReason,
                'is_new' => 1,
                'number' => $queueNumber,
                'queue_number' => $queueNumber,
            ]);

            DB::commit();
            return new PatientResource($patient->loadMissing(['company', 'primaryDoctor', 'doctorVisit.doctor', 'doctorVisit.file']));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("New patient registration failed: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل تسجيل المريض.', 'error' => 'خطأ داخلي.' . $e->getMessage()], 500);
        }
    }

    /**
     * Create a new Patient record by cloning data, and then create a new DoctorVisit.
     * A new File record is created and linked if no previous_visit_id is provided,
     * otherwise, the file_id from the previous visit is copied.
     */
    public function storeVisitFromHistory(Request $request, DoctorVisit $doctorVisit)
    {
        $validatedVisitData = $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'active_doctor_shift_id' => 'nullable|integer|exists:doctor_shifts,id',
            'reason_for_visit' => 'nullable|string|max:1000',
            'previous_visit_id' => 'nullable|integer|exists:doctorvisits,id,patient_id,' . $doctorVisit->patient_id,
        ]);

        $currentGeneralShift = Shift::open()->latest('created_at')->first();
        if (!$currentGeneralShift) {
            return response()->json(['message' => 'لا توجد وردية عيادة مفتوحة حالياً.'], 400);
        }

        $fileToUseId = null;
        $previousVisit = null;

        if (!empty($validatedVisitData['previous_visit_id'])) {
            $previousVisit = DoctorVisit::find($validatedVisitData['previous_visit_id']);
            $fileToUseId = $previousVisit?->file_id; // Copy existing file_id
        }

        // If no previous visit was specified to copy file_id from, OR if that visit had no file_id,
        // AND if the existingPatient's latest visit also doesn't provide a file_id, create a new file.
        // This logic assumes you want to reuse file_id if available from history.
        if (!$fileToUseId) {
            $latestVisitOfExistingPatient = $doctorVisit->patient->doctorVisit()->latest('visit_date')->first();
            $fileToUseId = $latestVisitOfExistingPatient?->file_id;
        }


        DB::beginTransaction();
        try {
            // 1. Create a new File record ONLY if we couldn't find one to copy
            if (!$fileToUseId) {
                $file = File::create();
                $fileToUseId = $file->id;
            }
            //the visit number is the number of  the in the general shift
            $visitLabNumber = DoctorVisit::where('shift_id', $currentGeneralShift->id)->count() + 1;
            $queueNumber = DoctorVisit::where('doctor_shift_id', $validatedVisitData['active_doctor_shift_id'])->count() + 1;
            // 2. Clone patient data to create a NEW patient record
            $newPatientData = $doctorVisit->patient->replicate()->fill([
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'visit_number' => $visitLabNumber,
                'created_at' => now(), // New record, new timestamps
                'updated_at' => now(),
                'doctor_id' => $validatedVisitData['doctor_id'],
                'result_auth' => false,
                'auth_date' => null,
                // Reset visit-specific flags from the old patient snapshot
                'is_lab_paid' => false,
                'lab_paid' => 0,
                'result_is_locked' => false,
                'sample_collected' => false,
                // Potentially update some demographics if the form was partially filled for this "new" visit using old data as base
                // 'phone' => $request->input('new_phone', $existingPatient->phone), // Example
            ])->toArray();
            // Ensure 'id' is not carried over from replication for create
            unset($newPatientData['id']);

            $newPatient = Patient::create($newPatientData);

            // 3. Create the new DoctorVisit for this NEW patient record
            $doctorVisit = $newPatient->doctorVisit()->create([
                'doctor_id' => $validatedVisitData['doctor_id'],
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'doctor_shift_id' => $validatedVisitData['active_doctor_shift_id'] ?? null,
                'file_id' => $fileToUseId, // Assign the determined file_id
                'visit_date' => Carbon::today(),
                'visit_time' => Carbon::now()->format('H:i:s'),
                'status' => 'waiting',
                'reason_for_visit' => $validatedVisitData['reason_for_visit'] ?? ($previousVisit?->reason_for_visit ?? 'متابعة'),
                'is_new' => false,
                'number' => $queueNumber, // Example: Using file_id as the visit/encounter number
                'queue_number' => $queueNumber, // Example: Using file_id as the visit/encounter number
            ]);
            DB::commit();

            return new DoctorVisitResource($doctorVisit->load(['patient', 'doctor', 'file']));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to store visit from history for original patient {$doctorVisit->patient->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل إنشاء الزيارة الجديدة من السجل.', 'error' => 'خطأ داخلي.' . $e->getMessage()], 500);
        }
    }
    public function searchExisting(Request $request)
    {
        $searchTerm = $request->validate([
            'term' => 'required|string|min:2',
        ])['term'];
    
        $visits = DoctorVisit::query()
            ->whereHas('patient', function ($query) use ($searchTerm) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('phone', 'LIKE', "%{$searchTerm}%");
                });
            })
            ->with(['patient', 'doctor'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    
        return PatientSearchResultResource::collection($visits);
    }
    

    /**
     * Display the specified patient.
     */
    public function show(Patient $patient)
    {
        // Load necessary relations for a detailed patient view
        $patient->load([
            'company',
            'subcompany',
            'companyRelation',
            'primaryDoctor:id,name',
            'user:id,name', // User who registered

        ]);
        return new PatientResource($patient);
    }

    /**
     * Update the specified patient in storage.
     */
    public function update(UpdatePatientRequest $request, Patient $patient)
    {
        $validatedData = $request->validated();
        // Exclude fields that are managed by other processes or shouldn't be mass updated here
        // For example, financial or specific clinical flags related to visits.

        $patient->update($validatedData);
        return new PatientResource($patient->loadMissing(['company', 'primaryDoctor']));
    }

    /**
     * Remove the specified patient from storage.
     */
    public function destroy(Patient $patient)
    {
        // Add extensive checks before deleting a patient. Soft deletes are highly recommended.
        // e.g., if patient has visits, lab requests, payments, etc.
        if ($patient->doctorVisits()->exists() /* || $patient->payments()->exists() || ... */) {
            return response()->json(['message' => 'لا يمكن حذف المريض لارتباطه بزيارات أو بيانات أخرى.'], 403);
        }
        // DB::transaction(function() use ($patient) {
        // Handle related data if necessary
        $patient->delete();
        // });
        return response()->json(null, 204);
    }
    // In PatientController.php
    public function visitHistory(Patient $patient)
    {
        // Get all doctor visits where the patient's phone number matches
        $patients = DoctorVisit::whereHas('patient', function ($query) use ($patient) {
            $query->where('phone', $patient->phone);
        })
            ->with(['doctor:id,name', 'requestedServices.service:id,name', 'patient', 'doctor'])
            ->orderBy('created_at', 'desc')
            ->get();

        return DoctorVisitResource::collection($patients);
    }
    public function getRecentLabActivityPatients(Request $request)
    {
        // if (!Auth::user()->can('view lab_workstation_patient_dropdown')) { /* ... */ }

        $limit = $request->input('limit', 30);

        // Find patients who had lab requests, ordered by the most recent lab request's creation time or visit time.
        // This query can be complex to optimize for "most recent based on lab activity".
        // Simpler approach: Patients with most recent visits that included lab requests.
        $patients = DoctorVisit::whereHas('patientLabRequests', function ($query) {
            $query->where('valid', true);
        })->with(['patient'=>function($query){
            $query->select('id','name','phone');
        }])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
            
        // The resource needs to handle 'latest_visit_id' if it's not a direct attribute
        return PatientStrippedResource::collection($patients)->additional([
            'meta' => ['note' => 'latest_visit_id refers to the DoctorVisit ID']
        ]);
    }
      /**
     * Search for DoctorVisits by patient name for Autocomplete.
     * Returns recent visits matching the patient name.
     */
    public function searchRecentDoctorVisitsByPatientName(Request $request)
    {
        // if (!Auth::user()->can('search_doctor_visits')) { /* ... */ }

        $request->validate([
            'patient_name_search' => 'required|string|min:2',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $searchTerm = $request->patient_name_search;
        $limit = $request->input('limit', 15); // Default limit for autocomplete

        $visits = DoctorVisit::with([
                'patient:id,name,phone', // Eager load basic patient info
                'doctor:id,name'         // Eager load basic doctor info
            ])
            ->whereHas('patient', function ($query) use ($searchTerm) {
                $query->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('phone', 'LIKE', "%{$searchTerm}%"); // Optionally search by phone too
            })
            // Optionally, filter for visits that actually have lab requests if this is for lab workstation
            // ->whereHas('labRequests') 
            ->orderBy('visit_date', 'desc') // Most recent visits first
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();
            
        // Filter out visits that do not have lab requests (if strictly for lab workstation)
        // Or do this in the initial whereHas if more performant for your DB
        $visitsWithLabs = $visits->filter(function ($visit) {
            // This requires labRequests relation to be defined on DoctorVisit
            // If labRequests are linked via pid, this check is more complex here
            // For now, assuming a direct or indirect link is checkable.
            // If labRequests are linked via patient_id directly (pid), this could be:
            // return $visit->patient->labRequests()->whereDate('created_at', $visit->visit_date)->exists();
            // Or if LabRequest table has a visit_id (even if not an FK constraint, but populated):
            // return \App\Models\LabRequest::where('doctor_visit_id_placeholder', $visit->id)->exists();

            // Simpler: If you always create DoctorVisit even for lab, then just return.
            // If the visit itself implies lab work, then no extra check needed.
            // For now, let's assume any visit returned could be relevant.
            // Frontend can later check if it has lab requests when loading full details.
            return true; 
        });


        return RecentDoctorVisitSearchResource::collection($visitsWithLabs);
    }
}

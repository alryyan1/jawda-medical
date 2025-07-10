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
use App\Models\Doctor;
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
        $query = Patient::with(['company', 'primaryDoctor:id,name', 'doctor','doctorVisit']); // Eager load common relations

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
     * Toggle the result lock status for a patient.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Patient  $patient
     * @return \App\Http\Resources\PatientResource|\Illuminate\Http\JsonResponse
     */
    public function toggleResultLock(Request $request, Patient $patient)
    {
        // Add permission check: e.g., can('lock lab_results', $patient)
        // if (!Auth::user()->can('manage_result_lock', $patient)) { // Example permission
        //     return response()->json(['message' => 'Unauthorized to change result lock status.'], 403);
        // }

        $request->validate([
            'lock' => 'required|boolean', // Expecting true to lock, false to unlock
        ]);

        if ($patient->result_is_locked === $request->boolean('lock')) {
            $status = $request->boolean('lock') ? 'locked' : 'unlocked';
            return response()->json([
                'message' => "Results are already {$status}.",
                'data' => new PatientResource($patient->fresh()) // Return current state
            ], 200); // Or 409 Conflict if preferred
        }

        $patient->result_is_locked = $request->boolean('lock');
        
        // If locking, you might want to log who locked it and when,
        // potentially in an audit trail or separate fields on the patient model.
        // if ($request->boolean('lock')) {
        //     $patient->result_locked_by = Auth::id();
        //     $patient->result_locked_at = now();
        // } else {
        //     $patient->result_locked_by = null;
        //     $patient->result_locked_at = null;
        // }
        
        $patient->save();

        $action = $request->boolean('lock') ? 'locked' : 'unlocked';
        return response()->json([
            'message' => "Patient results have been successfully {$action}.",
            'data' => new PatientResource($patient->fresh()->load(['company', 'primaryDoctor'])) // Reload relations for consistency
        ]);
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
            $latestVisitOfExistingPatient = $doctorVisit->patient->doctorVisit()->latest('created_at')->first();
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

            return new DoctorVisitResource($doctorVisit->load(['patient.subcompany', 'patient.doctor', 'file']));
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
     /**
     * Store a new patient and create a lab-centric visit from the Lab Reception page.
     * This method does not require an active doctor shift but links to a general clinic shift.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \App\Http\Resources\PatientResource|\Illuminate\Http\JsonResponse
     */
    public function storeFromLab(Request $request)
    {
        // Add a specific permission check for this action
        // if (!Auth::user()->can('register lab_patient')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        // Validation tailored for lab registration
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'gender' => 'required|in:male,female',
            'age_year' => 'nullable|integer|min:0|max:120',
            'age_month' => 'nullable|integer|min:0|max:11',
            'age_day' => 'nullable|integer|min:0|max:30',
            'address' => 'nullable|string|max:1000',
            'doctor_id' => 'required|integer|exists:doctors,id', // Referring doctor is required
            // No company/insurance fields needed if lab is always cash, otherwise add them here.
        ]);

        $currentGeneralShift = Shift::open()->latest('created_at')->first();
        if (!$currentGeneralShift) {
            return response()->json(['message' => 'No open clinic shift available to create a visit.'], 400);
        }
        //the visit number is the number of  the in the general shift
        $visitLabNumber = DoctorVisit::where('shift_id', $currentGeneralShift->id)->count() + 1;


        // Find the latest active shift for the selected referring doctor.
        // This is important for correctly assigning any doctor-related credit later.
        $activeDoctorShift = DoctorShift::where('doctor_id', $validatedData['doctor_id'])
                                        ->latest('id')
                                        ->first();

                                        if($activeDoctorShift){
                                           
                                        }else{
                                            //create a new shift
                                            $activeDoctorShift = DoctorShift::create([
                                                'doctor_id' => $validatedData['doctor_id'],
                                                'start_time' => Carbon::now(),
                                                'end_time' => Carbon::now()->addHours(1),
                                                'status' => true,
                                            ]);
                                        }

        DB::beginTransaction();
        try {
            // Your logic for finding an existing patient file can be reused here
            // For simplicity, we'll create a new file for each new lab visit encounter
            $file = File::create();

            // Create the new Patient record for this encounter
            // A new patient record is created for each visit to capture their state at that time.
            $patient = Patient::create([
                'name' => $validatedData['name'],
                'phone' => $validatedData['phone'],
                'gender' => $validatedData['gender'] ?? 'male',
                'age_year' => $validatedData['age_year'] ?? 0   ,
                'age_month' => $validatedData['age_month'] ?? 0,
                'age_day' => $validatedData['age_day'] ?? 0,
                'address' => $validatedData['address'] ?? '',
                'doctor_id' => $validatedData['doctor_id'], // Store the referring doctor
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'auth_date' => null,
                'result_auth' => false,
                'result_is_locked' => false,
                'sample_collected' => false,
                'is_lab_paid' => false,
                'lab_paid' => 0,
                'referred' => Doctor::find($validatedData['doctor_id'])->name,
                'discount_comment' => '',
                'visit_number' => $visitLabNumber,
                

            ]);

            // Create the DoctorVisit record linked to this new Patient record
            $doctorVisit = $patient->doctorVisit()->create([
                'doctor_id' => $validatedData['doctor_id'], // Referring doctor
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'doctor_shift_id' => $activeDoctorShift?->id, // Can be null if doctor isn't on shift
                'file_id' => $file->id,
                'visit_date' => Carbon::today(),
                'visit_time' => Carbon::now()->format('H:i:s'),
                'status' => 'lab_pending', // A specific status for lab reception visits
                'is_new' => true,
                'only_lab' => true, // CRUCIAL FLAG for this workflow
            ]);

            DB::commit();
            
            // Return the patient resource, ensuring it includes the new doctorVisit relation
            // The PatientResource should be configured to conditionally include this.
            $patient->load('doctorVisit');
            
            return new PatientResource($patient);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Lab patient registration failed: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to register patient for lab.', 'error_details' => $e->getMessage()], 500);
        }
    }

    /**
     * Search for patients and return their recent visits for an Autocomplete component.
     */
    public function searchPatientVisitsForAutocomplete(Request $request)
    {
        $request->validate([
            'term' => 'required|string|min:2',
        ]);

        $searchTerm = $request->term;

        // Find recent visits for patients matching the search term
        $visits = DoctorVisit::with('patient:id,name,phone')
            ->whereHas('patient', function ($query) use ($searchTerm) {
                $query->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('phone', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('id', $searchTerm);
            })
            ->select('id', 'patient_id', 'visit_date') // Select only what's needed
            ->latest('visit_date') // Order by most recent visits first
            ->limit(15) // Limit the number of results for performance
            ->get();

        // Transform the data into the format the frontend Autocomplete expects
        $formattedResults = $visits->map(function ($visit) {
            return [
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                // Create a user-friendly label for the dropdown
                'autocomplete_label' => "{$visit->patient->name} (#{$visit->patient->id}) - Visit on {$visit->visit_date->format('d-M-Y')}",
            ];
        });

        return response()->json(['data' => $formattedResults]);
    }
     /**
     * Create a new lab-only visit for an existing patient.
     */
    public function createLabVisitForExistingPatient(Request $request, DoctorVisit $doctorVisit)
    {
        $patient = $doctorVisit->patient;
        // Add permission check, e.g., can('create lab_visit')
        $validated = $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id', // Referring doctor
            'company_id' => 'nullable|integer|exists:companies,id', // Company for insurance patients
            'reason_for_visit' => 'nullable|string|max:1000',
        ]);

        $currentGeneralShift = Shift::open()->latest('created_at')->first();
        if (!$currentGeneralShift) {
            return response()->json(['message' => 'No open clinic shift is available to create a visit.'], 400);
        }
        DB::beginTransaction();
        try {
            // Get file_id from the patient's current doctorVisit or create a new file
            $fileToUseId = $patient->doctorVisit?->file_id;
            
            // If for some reason no previous visit had a file, create a new one
            if (!$fileToUseId) {
                $file = File::create();
                $fileToUseId = $file->id;
            }

            $visitLabNumber = DoctorVisit::where('shift_id', $currentGeneralShift->id)->count() + 1;

            // Since Patient has only one doctorVisit, we need to create a new Patient record
            // and then create the doctorVisit for that new patient record
            $newPatient = Patient::create([
                'name' => $patient->name,
                'phone' => $patient->phone,
                'gender' => $patient->gender,
                'age_year' => $patient->age_year,
                'age_month' => $patient->age_month,
                'age_day' => $patient->age_day,
                'address' => $patient->address,
                'company_id' => $validated['company_id'] ?? $patient->company_id, // Use new company_id if provided, otherwise keep original
                'subcompany_id' => $patient->subcompany_id,
                'company_relation_id' => $patient->company_relation_id,
                'guarantor' => $patient->guarantor,
                'insurance_no' => $patient->insurance_no,
                'doctor_id' => $validated['doctor_id'],
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'visit_number' => $visitLabNumber,
                'result_auth' => false,
                'referred' => 'no',
                'discount_comment' => '',
            ]);

            // Create the DoctorVisit record for the new patient
            $doctorVisit = $newPatient->doctorVisit()->create([
                'doctor_id' => $validated['doctor_id'],
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'doctor_shift_id' => null, // Not tied to a doctor's active shift session in the clinic
                'file_id' => $fileToUseId,
                'visit_date' => Carbon::today(),
                'visit_time' => Carbon::now()->format('H:i:s'),
                'status' => 'lab_pending', // A status indicating it's waiting for lab work
                'reason_for_visit' => $validated['reason_for_visit'] ?? 'Lab Request',
                'is_new' => false, // It's a visit for an existing patient
                'number' => $visitLabNumber,
                'only_lab' => true, // CRUCIAL: This marks it as a direct lab visit
            ]);
            
            DB::commit();

            // Load the doctorVisit relationship for the new patient
            $newPatient->load('doctorVisit');

            return new \App\Http\Resources\PatientResource($newPatient);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create lab visit for existing patient {$patient->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to create lab visit.', 'error' => $e->getMessage()], 500);
        }
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

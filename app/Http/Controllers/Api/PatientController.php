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
        $query = Patient::with(['company', 'primaryDoctor:id,name']); // Eager load common relations

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


        DB::beginTransaction();
        try {
            // Check for existing patient with same phone number or identical name
            $existingPatient = null;
            $fileToUseId = null;
            
            if (!empty($patientSpecificData['phone']) || !empty($patientSpecificData['name'])) {
                $existingPatient = Patient::where(function($query) use ($patientSpecificData) {
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
                'is_new' => !$existingPatient,
                'number' => $queueNumber,
                'queue_number' => $queueNumber,
            ]);

            DB::commit();
            return new PatientResource($patient->loadMissing(['company', 'primaryDoctor', 'doctorVisit.doctor', 'doctorVisit.file']));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("New patient registration failed: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل تسجيل المريض.', 'error' => 'خطأ داخلي.'.$e->getMessage()], 500);
        }
    }

    /**
     * Create a new Patient record by cloning data, and then create a new DoctorVisit.
     * A new File record is created and linked if no previous_visit_id is provided,
     * otherwise, the file_id from the previous visit is copied.
     */
    public function storeVisitFromHistory(Request $request, Patient $patient)
    {
        $validatedVisitData = $request->validate([
            'previous_visit_id' => 'nullable|integer|exists:doctorvisits,id,patient_id,'.$patient->id,
            'doctor_id' => 'required|integer|exists:doctors,id',
            'active_doctor_shift_id' => 'nullable|integer|exists:doctor_shifts,id',
            'reason_for_visit' => 'nullable|string|max:1000',
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
             $latestVisitOfExistingPatient = $patient->doctorVisit()->latest('visit_date')->first();
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
            $newPatientData = $patient->replicate()->fill([
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'visit_number' => $visitLabNumber, 
                'created_at' => now(), // New record, new timestamps
                'updated_at' => now(),
                'doctor_id' => $validatedVisitData['doctor_id'],
                 'result_auth' => false,
                // Reset visit-specific flags from the old patient snapshot
                'is_lab_paid' => false, 'lab_paid' => 0,
                'result_is_locked' => false, 'sample_collected' => false,
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
            Log::error("Failed to store visit from history for original patient {$patient->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل إنشاء الزيارة الجديدة من السجل.', 'error' => 'خطأ داخلي.'.$e->getMessage()], 500);
        }
    }
    public function searchExisting(Request $request)
    {
        // $this->authorize('view patients'); // Or a specific search permission
        $request->validate([
            'term' => 'required|string|min:2', // Min 2 chars to start search
        ]);

        $searchTerm = $request->term;

        // Search by name or phone
        // Eager load last visit with its doctor for context
        $patients = Patient::where(function ($query) use ($searchTerm) {
            $query->where('name', 'LIKE', "%{$searchTerm}%")
                ->orWhere('phone', 'LIKE', "%{$searchTerm}%");
        })
            ->with(['latestDoctorVisit.doctor:id,name']) // latestDoctorVisit is a hasOne relationship in Patient model
            ->limit(10) // Limit results for live search
            ->get();

        return PatientSearchResultResource::collection($patients);
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
            'doctorVisits' => function ($query) { // Load last few visits for history preview
                $query->with('doctor:id,name')->latest()->limit(5);
            }
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
}
